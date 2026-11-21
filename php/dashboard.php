<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if (empty($_SESSION['author_id'])) {
    redirectTo('../componnent/login.html?error=unauthorized');
}

$pdo = getDBConnection();
$authorId = (int) $_SESSION['author_id'];
$csrfToken = generateCsrfToken();

$authorStmt = $pdo->prepare('
    SELECT id, full_name, pen_name, email, bio, role, last_login_at, created_at
    FROM authors
    WHERE id = :id
    LIMIT 1
');
$authorStmt->execute([':id' => $authorId]);
$authorProfile = $authorStmt->fetch();

if (!$authorProfile) {
    redirectTo('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectTo('dashboard.php?error=csrf_invalid');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_article') {
        $title    = sanitizeInput($_POST['title'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? 'Opinion');
        $summary  = sanitizeInput($_POST['summary'] ?? '');
        $content  = sanitizeInput($_POST['content'] ?? '');
        $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        if (mb_strlen($title) < 4 || mb_strlen($title) > 200 || mb_strlen($summary) < 10 || mb_strlen($content) < 20) {
            redirectTo('dashboard.php?error=article_validation');
        }

        $allowedCategories = ['Opinion', 'Technology', 'Science', 'Literature', 'Guide'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'Opinion';
        }

        $slug = createSlug($title);
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

        $insArt = $pdo->prepare('
            INSERT INTO articles (author_id, title, slug, category, summary, content, status, published_at)
            VALUES (:author_id, :title, :slug, :category, :summary, :content, :status, :published_at)
        ');
        $insArt->execute([
            ':author_id'    => $authorId,
            ':title'        => $title,
            ':slug'         => $slug,
            ':category'     => $category,
            ':summary'      => $summary,
            ':content'      => $content,
            ':status'       => $status,
            ':published_at' => $publishedAt,
        ]);

        redirectTo('dashboard.php?status=article_saved');
    }

    if ($action === 'toggle_status') {
        $articleId = (int) ($_POST['article_id'] ?? 0);
        $findStmt = $pdo->prepare('SELECT id, status FROM articles WHERE id = :id AND author_id = :author_id LIMIT 1');
        $findStmt->execute([':id' => $articleId, ':author_id' => $authorId]);
        $art = $findStmt->fetch();

        if ($art) {
            $nextStatus = $art['status'] === 'published' ? 'draft' : 'published';
            $pubDate = $nextStatus === 'published' ? date('Y-m-d H:i:s') : null;
            $updStmt = $pdo->prepare('
                UPDATE articles
                SET status = :status, published_at = :pub_at
                WHERE id = :id AND author_id = :author_id
            ');
            $updStmt->execute([
                ':status'    => $nextStatus,
                ':pub_at'    => $pubDate,
                ':id'        => $articleId,
                ':author_id' => $authorId,
            ]);
            redirectTo('dashboard.php?status=status_updated');
        }
        redirectTo('dashboard.php?error=article_not_found');
    }

    if ($action === 'delete_article') {
        $articleId = (int) ($_POST['article_id'] ?? 0);
        $delStmt = $pdo->prepare('DELETE FROM articles WHERE id = :id AND author_id = :author_id');
        $delStmt->execute([':id' => $articleId, ':author_id' => $authorId]);
        redirectTo('dashboard.php?status=article_deleted');
    }

    if ($action === 'update_profile') {
        $newFullName = sanitizeInput($_POST['full_name'] ?? '');
        $newPenName  = sanitizeInput($_POST['pen_name'] ?? '');
        $newBio      = sanitizeInput($_POST['bio'] ?? '');

        if (mb_strlen($newFullName) < 3 || mb_strlen($newFullName) > 100 || mb_strlen($newBio) > 400) {
            redirectTo('dashboard.php?error=profile_validation');
        }

        $updProf = $pdo->prepare('
            UPDATE authors
            SET full_name = :full_name, pen_name = :pen_name, bio = :bio
            WHERE id = :id
        ');
        $updProf->execute([
            ':full_name' => $newFullName,
            ':pen_name'  => $newPenName !== '' ? $newPenName : null,
            ':bio'       => $newBio !== '' ? $newBio : null,
            ':id'        => $authorId,
        ]);

        $_SESSION['author_name'] = $newFullName;
        $_SESSION['author_pen']  = $newPenName;
        redirectTo('dashboard.php?status=profile_updated');
    }
}

$searchQuery = sanitizeInput($_GET['q'] ?? '');
$filterStatus = $_GET['filter'] ?? 'all';
if (!in_array($filterStatus, ['all', 'published', 'draft'], true)) {
    $filterStatus = 'all';
}

$sql = 'SELECT id, title, slug, category, summary, status, views_count, published_at, created_at FROM articles WHERE author_id = :author_id';
$params = [':author_id' => $authorId];

if ($filterStatus !== 'all') {
    $sql .= ' AND status = :status';
    $params[':status'] = $filterStatus;
}

if ($searchQuery !== '') {
    $sql .= ' AND (title LIKE :q OR category LIKE :q OR summary LIKE :q)';
    $params[':q'] = '%' . $searchQuery . '%';
}

$sql .= ' ORDER BY created_at DESC';
$artStmt = $pdo->prepare($sql);
$artStmt->execute($params);
$articles = $artStmt->fetchAll();

$statStmt = $pdo->prepare('
    SELECT
        COUNT(*) AS total_articles,
        SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) AS total_published,
        SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) AS total_drafts
    FROM articles
    WHERE author_id = :author_id
');
$statStmt->execute([':author_id' => $authorId]);
$stats = $statStmt->fetch() ?: ['total_articles' => 0, 'total_published' => 0, 'total_drafts' => 0];

$logStmt = $pdo->prepare('
    SELECT ip_address, user_agent, status, created_at
    FROM login_logs
    WHERE author_id = :author_id
    ORDER BY id DESC
    LIMIT 6
');
$logStmt->execute([':author_id' => $authorId]);
$loginLogs = $logStmt->fetchAll();

$safeName  = escapeHtml($authorProfile['full_name']);
$safePen   = escapeHtml($authorProfile['pen_name'] ?? '-');
$safeEmail = escapeHtml($authorProfile['email']);
$safeBio   = escapeHtml($authorProfile['bio'] ?? '');
$safeRole  = escapeHtml(strtoupper($authorProfile['role'] ?? 'author'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Dashboard — Author Studio</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="page-wrapper dashboard-wrapper">
        <section class="card dashboard-card">
            <header class="dashboard-header">
                <div>
                    <span class="badge">Active Session &bull; <?= $safeRole ?></span>
                    <h1>Hello, <?= $safeName ?>!</h1>
                    <p class="subtitle">
                        Email: <strong><?= $safeEmail ?></strong>
                        <?php if (!empty($authorProfile['pen_name'])): ?>
                            &bull; Pen Name: <strong><?= $safePen ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <button type="button" id="theme-toggle" class="btn btn-ghost btn-sm">Dark Mode</button>
                    <a href="logout.php" class="btn btn-danger btn-sm">Sign Out</a>
                </div>
            </header>

            <div id="alert-box" class="alert" role="alert" hidden></div>

            <div class="dashboard-grid">
                <article class="stat-box">
                    <h2>Total Articles</h2>
                    <p><?= (int) ($stats['total_articles'] ?? 0) ?></p>
                </article>
                <article class="stat-box">
                    <h2>Published</h2>
                    <p><?= (int) ($stats['total_published'] ?? 0) ?></p>
                </article>
                <article class="stat-box">
                    <h2>Saved Drafts</h2>
                    <p><?= (int) ($stats['total_drafts'] ?? 0) ?></p>
                </article>
                <article class="stat-box">
                    <h2>Last Sign In</h2>
                    <p><?= escapeHtml((string) ($authorProfile['last_login_at'] ?? 'Just now')) ?></p>
                </article>
            </div>

            <section class="panel-section">
                <h2>Write New Article</h2>
                <form action="dashboard.php" method="POST" class="article-form">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_article">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="title">Article Title</label>
                            <input type="text" id="title" name="title" placeholder="Minimum 4 characters" required>
                        </div>

                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="Opinion">Opinion</option>
                                <option value="Technology">Technology</option>
                                <option value="Science">Science</option>
                                <option value="Literature">Literature</option>
                                <option value="Guide">Guide</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="summary">Summary (Minimum 10 characters)</label>
                        <input type="text" id="summary" name="summary" placeholder="Brief overview of your article..." required>
                    </div>

                    <div class="form-group">
                        <label for="content">Article Content (Minimum 20 characters)</label>
                        <textarea id="content" name="content" rows="4" placeholder="Write the full content of your article..." required></textarea>
                    </div>

                    <div class="form-row-inline">
                        <div class="form-group compact-group">
                            <label for="status">Publication Status</label>
                            <select id="status" name="status">
                                <option value="draft">Save as Draft</option>
                                <option value="published">Publish Immediately</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Article</button>
                    </div>
                </form>
            </section>

            <section class="panel-section">
                <div class="section-toolbar">
                    <h2>My Articles</h2>
                    <form method="GET" action="dashboard.php" class="filter-form">
                        <input
                            type="text"
                            name="q"
                            value="<?= escapeHtml($searchQuery) ?>"
                            placeholder="Search title or category..."
                        >
                        <select name="filter">
                            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    </form>
                </div>

                <?php if (empty($articles)): ?>
                    <p class="empty-state">No articles match the current filter criteria.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title &amp; Summary</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles as $art): ?>
                                    <tr>
                                        <td>
                                            <strong><?= escapeHtml($art['title']) ?></strong><br>
                                            <small class="text-muted"><?= escapeHtml($art['summary']) ?></small>
                                        </td>
                                        <td><span class="pill"><?= escapeHtml($art['category']) ?></span></td>
                                        <td>
                                            <span class="status-badge status-<?= escapeHtml($art['status']) ?>">
                                                <?= escapeHtml(strtoupper($art['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <form method="POST" action="dashboard.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="article_id" value="<?= (int) $art['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-xs">
                                                    <?= $art['status'] === 'published' ? 'Move to Draft' : 'Publish' ?>
                                                </button>
                                            </form>
                                            <form method="POST" action="dashboard.php" class="inline-form" onsubmit="return confirm('Delete this article permanently?');">
                                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_article">
                                                <input type="hidden" name="article_id" value="<?= (int) $art['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel-section">
                <h2>Author Profile Settings</h2>
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="profile_full_name">Full Name</label>
                            <input type="text" id="profile_full_name" name="full_name" value="<?= $safeName ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="profile_pen_name">Pen Name</label>
                            <input type="text" id="profile_pen_name" name="pen_name" value="<?= $safePen === '-' ? '' : $safePen ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="profile_bio">Biography</label>
                        <textarea id="profile_bio" name="bio" rows="2"><?= $safeBio ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-secondary">Update Profile</button>
                </form>
            </section>

            <section class="panel-section">
                <h2>Recent Sign-In Activity</h2>
                <div class="table-responsive">
                    <table class="data-table compact-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Device / Browser</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loginLogs as $log): ?>
                                <tr>
                                    <td><?= escapeHtml((string) $log['created_at']) ?></td>
                                    <td><code><?= escapeHtml((string) $log['ip_address']) ?></code></td>
                                    <td>
                                        <span class="status-badge status-<?= escapeHtml((string) $log['status']) ?>">
                                            <?= escapeHtml(strtoupper((string) $log['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= escapeHtml((string) $log['user_agent']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>

    <script src="../assets/js/script.js"></script>
</body>
</html>
