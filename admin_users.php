<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\SecurityLogger;
use App\Repositories\UserRepository;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
if (!Auth::isAdmin()) {
    SecurityLogger::unauthorizedAccess('admin_users', context: ['user_id' => Auth::userId()]);
    http_response_code(403);
    exit(t('error.access_denied'));
}

$repo = new UserRepository();
$currentUserId = (int) Auth::userId();

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $target = $repo->findById($targetId);
            if ($target === null) {
                throw new RuntimeException(t('error.user_not_found'));
            }

            if ($targetId === $currentUserId) {
                throw new RuntimeException(t('error.cannot_delete_self'));
            }

            if ((string) $target['role'] === 'admin' && $repo->countAdmins() <= 1) {
                throw new RuntimeException(t('error.last_admin_cannot_be_deleted'));
            }

            $repo->deleteById($targetId);
            Flash::set('success', t('flash.user_deleted'));
            redirect('/admin_users.php');
        }

        throw new RuntimeException(t('error.unknown_action'));
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
        redirect('/admin_users.php');
    }
}

$users = $repo->all();

$title = t('admin_users.title');
$active = 'admin-users';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e(t('admin_users.heading')) ?></h2>
    <p><?= e(t('admin_users.subtitle')) ?></p>
  </div>
  <a class="btn primary" href="/user_form.php"><?= e(t('admin_users.create_user')) ?></a>
</section>

<section class="card">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th class="hide-mobile">ID</th>
        <th><?= e(t('auth.email')) ?></th>
        <th><?= e(t('common.role')) ?></th>
        <th class="hide-mobile"><?= e(t('admin_users.last_login')) ?></th>
        <th class="hide-mobile"><?= e(t('common.created_at')) ?></th>
        <th><?= e(t('common.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td class="hide-mobile"><?= (int) $user['id'] ?></td>
          <td><?= e((string) $user['email']) ?></td>
          <td><?= e(t((string) $user['role'] === 'admin' ? 'role.admin' : 'role.user')) ?></td>
          <td class="hide-mobile"><?= e((string) ($user['last_login_at'] ?? '-')) ?></td>
          <td class="hide-mobile"><?= e((string) $user['created_at']) ?></td>
          <td class="actions">
            <a href="/user_form.php?id=<?= (int) $user['id'] ?>"><?= e(t('common.edit')) ?></a>
            <?php if ((int) $user['id'] !== $currentUserId): ?>
              <form method="post" action="/admin_users.php" class="inline-form" onsubmit="return confirm('<?= e(t('confirm.delete_user')) ?>');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                <button class="btn danger icon-btn" type="submit" title="<?= e(t('common.delete')) ?>" aria-label="<?= e(t('common.delete')) ?>">
                  <svg class="trash-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z" fill="currentColor"></path>
                  </svg>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($users === []): ?>
        <tr><td colspan="6"><?= e(t('admin_users.empty')) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
