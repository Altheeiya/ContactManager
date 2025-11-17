<?php
session_start();


if (!isset($_SESSION['contacts'])) {
    $_SESSION['contacts'] = [];
}
if (!isset($_SESSION['started_at'])) {
    $_SESSION['started_at'] = time();
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}


function sanitize($str)
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
function redirect($params = [])
{
    $base = basename(__FILE__);
    if (!empty($params)) {
        $qs = http_build_query($params);
        header("Location: {$base}?{$qs}");
    } else {
        header("Location: {$base}");
    }
    exit;
}

function validate_contact($data)
{
    $errors = [];

    // Nama
    $nama = trim($data['nama'] ?? '');
    if ($nama === '' || mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama minimal 3 karakter';
    } elseif (mb_strlen($nama) > 50) {
        $errors['nama'] = 'Nama maksimal 50 karakter';
    }

    // Email
    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Email wajib diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid';
    }

    $telepon = trim($data['telepon'] ?? '');
    if ($telepon === '') {
        $errors['telepon'] = 'Nomor telepon wajib diisi';
    } elseif (!preg_match('/^[0-9]{10,13}$/', $telepon)) {
        $errors['telepon'] = 'Nomor telepon harus 10-13 digit';
    }

    $allowed = ['keluarga', 'teman', 'kerja', 'bisnis', 'lainnya'];
    $kategori = $data['kategori'] ?? '';
    if (!in_array($kategori, $allowed, true)) {
        $errors['kategori'] = 'Pilih kategori yang valid';
    }

    $alamat = trim($data['alamat'] ?? '');
    if ($alamat !== '' && mb_strlen($alamat) > 200) {
        $errors['alamat'] = 'Alamat maksimal 200 karakter';
    }

    return [$errors, [
        'nama' => $nama,
        'email' => $email,
        'telepon' => $telepon,
        'kategori' => $kategori,
        'alamat' => $alamat,
    ]];
}

$flash = ['type' => null, 'msg' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $flash = ['type' => 'danger', 'msg' => 'CSRF token tidak valid.'];
        redirect(['flash' => $flash['type'], 'msg' => $flash['msg']]);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        [$errors, $clean] = validate_contact($_POST);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['old'] = $clean;
            redirect(['tab' => 'form']);
        }

        $contact = $clean;
        $contact['id'] = uniqid('c', true);
        $contact['created_at'] = time();
        $_SESSION['contacts'][] = $contact;
        $flash = ['type' => 'success', 'msg' => 'PLAYER ADDED SUCCESSFULLY'];
        redirect(['flash' => $flash['type'], 'msg' => $flash['msg']]);
    }

    if ($action === 'update') {
        $id = $_POST['id'] ?? '';
        [$errors, $clean] = validate_contact($_POST);
        if ($errors) {
            $_SESSION['form_errors_edit'] = $errors;
            $_SESSION['old_edit'] = array_merge($clean, ['id' => $id]);
            redirect(['edit' => $id]);
        }

        foreach ($_SESSION['contacts'] as &$c) {
            if ($c['id'] === $id) {
                $c = array_merge($c, $clean);
                break;
            }
        }
        unset($c);
        $flash = ['type' => 'success', 'msg' => 'PLAYER DATA UPDATED'];
        redirect(['flash' => $flash['type'], 'msg' => $flash['msg']]);
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $_SESSION['contacts'] = array_values(array_filter(
            $_SESSION['contacts'],
            fn($c) => $c['id'] !== $id
        ));
        $flash = ['type' => 'success', 'msg' => 'PLAYER ELIMINATED'];
        redirect(['flash' => $flash['type'], 'msg' => $flash['msg']]);
    }

    if ($action === 'reset_session') {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }
}


$q = trim($_GET['q'] ?? '');
$filterKategori = $_GET['filterKategori'] ?? '';
$editId = $_GET['edit'] ?? null;

$contacts = $_SESSION['contacts'];
if ($filterKategori !== '') {
    $contacts = array_filter($contacts, fn($c) => $c['kategori'] === $filterKategori);
}
if ($q !== '') {
    $qLower = mb_strtolower($q);
    $contacts = array_filter($contacts, function ($c) use ($qLower) {
        return str_contains(mb_strtolower($c['nama']), $qLower)
            || str_contains(mb_strtolower($c['email']), $qLower)
            || str_contains(mb_strtolower($c['telepon']), $qLower);
    });
}

$editData = null;
if ($editId) {
    foreach ($_SESSION['contacts'] as $c) {
        if ($c['id'] === $editId) {
            $editData = $c;
            break;
        }
    }
}

$old = $_SESSION['old'] ?? ['nama' => '', 'email' => '', 'telepon' => '', 'kategori' => '', 'alamat' => ''];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['old'], $_SESSION['form_errors']);

$oldEdit = $_SESSION['old_edit'] ?? null;
$errorsEdit = $_SESSION['form_errors_edit'] ?? [];
unset($_SESSION['old_edit'], $_SESSION['form_errors_edit']);

$flashType = $_GET['flash'] ?? null;
$flashMsg = $_GET['msg'] ?? null;

$elapsed = time() - ($_SESSION['started_at'] ?? time());
$minutes = floor($elapsed / 60);
$seconds = $elapsed % 60;
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Manager - Arcade Edition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="py-3 shadow-sm bg-gradient-primary">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-wrap"><i class="bi bi-joystick"></i></div>
                <h1 class="h4 m-0 fw-bold text-white">INSERT COIN TO START</h1>
            </div>
            <div class="text-end text-white small">
                <div style="font-family: 'VT323'; font-size: 1.2rem;">PLAYER 1 CONTACTS: <span
                        class="badge bg-light text-primary fw-semibold"><?php echo count($_SESSION['contacts']); ?></span>
                </div>
                <div style="font-family: 'VT323'; font-size: 1.2rem;">TIME:
                    <?= "" . sprintf('%02d:%02d', $minutes, $seconds) ?></div>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <?php if ($flashType && $flashMsg): ?>
            <div class="alert alert-<?php echo sanitize($flashType); ?> fade show" role="alert">
                <?php echo sanitize($flashMsg); ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow border-0">
                        <div class="card-header bg-primary text-white">
                            <h2 class="h5 mb-0"><i class="bi bi-person-plus me-2"></i>NEW PLAYER ENTRY</h2>
                        </div>
                        <div class="card-body">
                            <form method="post" novalidate>
                                <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf']; ?>">
                                <input type="hidden" name="action" value="add">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="nama" class="form-label">PLAYER NAME <span
                                                class="text-danger">*</span></label>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>"
                                            id="nama" name="nama" value="<?php echo sanitize($old['nama']); ?>"
                                            minlength="3" maxlength="50" required placeholder="ENTER NAME">
                                        <?php if (isset($errors['nama'])): ?>
                                        <div class="invalid-feedback"><?php echo sanitize($errors['nama']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email" class="form-label">EMAIL ADDRESS <span
                                                class="text-danger">*</span></label>
                                        <input type="email"
                                            class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                            id="email" name="email" value="<?php echo sanitize($old['email']); ?>"
                                            required placeholder="user@domain.com">
                                        <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo sanitize($errors['email']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="telepon" class="form-label">PHONE NO <span
                                                class="text-danger">*</span></label>
                                        <input type="tel"
                                            class="form-control <?php echo isset($errors['telepon']) ? 'is-invalid' : ''; ?>"
                                            id="telepon" name="telepon" value="<?php echo sanitize($old['telepon']); ?>"
                                            pattern="[0-9]{10,13}" required placeholder="08xxxxxxxxxx">
                                        <?php if (isset($errors['telepon'])): ?>
                                        <div class="invalid-feedback"><?php echo sanitize($errors['telepon']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="kategori" class="form-label">CATEGORY <span
                                                class="text-danger">*</span></label>
                                        <select
                                            class="form-select <?php echo isset($errors['kategori']) ? 'is-invalid' : ''; ?>"
                                            id="kategori" name="kategori" required>
                                            <option value="">SELECT CLASS</option>
                                            <?php
                                            $opts = ['keluarga' => 'Keluarga', 'teman' => 'Teman', 'kerja' => 'Kerja', 'bisnis' => 'Bisnis', 'lainnya' => 'Lainnya'];
                                            foreach ($opts as $val => $label):
                                                $sel = $old['kategori'] === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            endforeach;
                                            ?>
                                        </select>
                                        <?php if (isset($errors['kategori'])): ?>
                                        <div class="invalid-feedback"><?php echo sanitize($errors['kategori']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-12">
                                        <label for="alamat" class="form-label">LOCATION BASE</label>
                                        <textarea
                                            class="form-control <?php echo isset($errors['alamat']) ? 'is-invalid' : ''; ?>"
                                            id="alamat" name="alamat" rows="3" maxlength="200"
                                            placeholder="Coordinates / Address (Optional)"><?php echo sanitize($old['alamat']); ?></textarea>
                                        <?php if (isset($errors['alamat'])): ?>
                                        <div class="invalid-feedback"><?php echo sanitize($errors['alamat']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="bi bi-save me-2"></i>SAVE ENTRY
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-2"></i>RESET
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card shadow border-0">
                        <div
                            class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h2 class="h5 mb-0"><i class="bi bi-card-checklist me-2"></i>HIGH SCORES / CONTACTS</h2>
                            <form class="d-flex gap-2" method="get">
                                <select class="form-select form-select-sm" name="filterKategori"
                                    style="max-width:180px;">
                                    <option value="">ALL CLASSES</option>
                                    <?php
                                    foreach ($opts as $val => $label):
                                        $sel = ($filterKategori === $val) ? 'selected' : '';
                                        echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                    endforeach;
                                    ?>
                                </select>
                                <input type="search" class="form-control form-control-sm" name="q"
                                    placeholder="SEARCH PLAYER..." value="<?php echo sanitize($q); ?>"
                                    style="max-width:200px;">
                                <button class="btn btn-sm btn-outline-light" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-body">

                            <?php if (empty($contacts)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-inbox display-1"></i>
                                <div class="mt-2">NO DATA FOUND</div>
                                <small>Insert new player via the form above</small>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($contacts as $c): ?>
                                <div class="list-group-item py-3">
                                    <div class="d-flex justify-content-between flex-wrap gap-3">
                                        <div>
                                            <div class="fw-bold h6 mb-1"><?php echo sanitize($c['nama']); ?></div>
                                            <div class="text-muted small"><i
                                                    class="bi bi-envelope me-1"></i><?php echo sanitize($c['email']); ?>
                                                · <i
                                                    class="bi bi-telephone ms-2 me-1"></i><?php echo sanitize($c['telepon']); ?>
                                            </div>
                                            <div class="mt-1">
                                                <?php
                                                        $badgeMap = [
                                                            'keluarga' => 'primary',
                                                            'teman' => 'success',
                                                            'kerja' => 'warning',
                                                            'bisnis' => 'info',
                                                            'lainnya' => 'secondary',
                                                        ];
                                                        $b = $badgeMap[$c['kategori']] ?? 'secondary';
                                                        ?>
                                                <span
                                                    class="badge text-bg-<?php echo $b; ?> text-uppercase"><?php echo sanitize($c['kategori']); ?></span>
                                            </div>
                                            <?php if (!empty($c['alamat'])): ?>
                                            <div class="small mt-2 text-secondary"><i
                                                    class="bi bi-geo-alt me-1"></i><?php echo sanitize($c['alamat']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-start gap-2">
                                            <a href="?edit=<?php echo urlencode($c['id']); ?>"
                                                class="btn btn-sm btn-outline-primary"><i
                                                    class="bi bi-pencil-square me-1"></i>EDIT</a>
                                            <form method="post">
                                                <input type="hidden" name="csrf"
                                                    value="<?php echo $_SESSION['csrf']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id"
                                                    value="<?php echo sanitize($c['id']); ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i
                                                        class="bi bi-trash3 me-1"></i>DELETE</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="post" class="d-flex justify-content-end mt-3">
                        <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf']; ?>">
                        <input type="hidden" name="action" value="reset_session">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-power me-2"></i>SHUT
                            DOWN (END SESSION)</button>
                    </form>
                </div>
            </div>

            <?php if ($editData): ?>
            <div class="modal fade show" id="editModal" tabindex="-1"
                style="display:block; background-color: rgba(0,0,0,.8);">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">MODIFY PLAYER DATA</h5>
                            <a href="index.php" class="btn-close" aria-label="Close"></a>
                        </div>
                        <form method="post" novalidate>
                            <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf']; ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo sanitize($editData['id']); ?>">
                            <div class="modal-body">
                                <?php $val = $oldEdit ? $oldEdit : $editData; ?>
                                <div class="mb-3">
                                    <label for="editNama" class="form-label">PLAYER NAME <span
                                            class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control <?php echo isset($errorsEdit['nama']) ? 'is-invalid' : ''; ?>"
                                        id="editNama" name="nama" value="<?php echo sanitize($val['nama']); ?>"
                                        minlength="3" maxlength="50" required>
                                    <?php if (isset($errorsEdit['nama'])): ?><div class="invalid-feedback">
                                        <?php echo sanitize($errorsEdit['nama']); ?></div><?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">EMAIL <span
                                            class="text-danger">*</span></label>
                                    <input type="email"
                                        class="form-control <?php echo isset($errorsEdit['email']) ? 'is-invalid' : ''; ?>"
                                        id="editEmail" name="email" value="<?php echo sanitize($val['email']); ?>"
                                        required>
                                    <?php if (isset($errorsEdit['email'])): ?><div class="invalid-feedback">
                                        <?php echo sanitize($errorsEdit['email']); ?></div><?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="editTelepon" class="form-label">PHONE NO <span
                                            class="text-danger">*</span></label>
                                    <input type="tel"
                                        class="form-control <?php echo isset($errorsEdit['telepon']) ? 'is-invalid' : ''; ?>"
                                        id="editTelepon" name="telepon" value="<?php echo sanitize($val['telepon']); ?>"
                                        pattern="[0-9]{10,13}" required>
                                    <?php if (isset($errorsEdit['telepon'])): ?><div class="invalid-feedback">
                                        <?php echo sanitize($errorsEdit['telepon']); ?></div><?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="editKategori" class="form-label">CATEGORY <span
                                            class="text-danger">*</span></label>
                                    <select
                                        class="form-select <?php echo isset($errorsEdit['kategori']) ? 'is-invalid' : ''; ?>"
                                        id="editKategori" name="kategori" required>
                                        <option value="">SELECT CLASS</option>
                                        <?php foreach ($opts as $valOpt => $label): $sel = ($val['kategori'] === $valOpt) ? 'selected' : '';
                                                echo "<option value=\"{$valOpt}\" {$sel}>{$label}</option>";
                                            endforeach; ?>
                                    </select>
                                    <?php if (isset($errorsEdit['kategori'])): ?><div class="invalid-feedback">
                                        <?php echo sanitize($errorsEdit['kategori']); ?></div><?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="editAlamat" class="form-label">LOCATION BASE</label>
                                    <textarea
                                        class="form-control <?php echo isset($errorsEdit['alamat']) ? 'is-invalid' : ''; ?>"
                                        id="editAlamat" name="alamat" rows="3"
                                        maxlength="200"><?php echo sanitize($val['alamat'] ?? ''); ?></textarea>
                                    <?php if (isset($errorsEdit['alamat'])): ?><div class="invalid-feedback">
                                        <?php echo sanitize($errorsEdit['alamat']); ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>SAVE
                                    CHANGES</button>
                                <a href="index.php" class="btn btn-outline-secondary">CANCEL</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <footer class="py-4 text-center text-white small bg-gradient-primary mt-4">
        <div class="container">
            <div class="footer-text mb-2">&copy; 2025 ARCADE SYSTEM v1.0</div>
            <div class="footer-text">GAME OVER - INSERT COIN</div>
            <div class="mt-2" style="font-family: 'VT323'; font-size: 1.2rem;">Dev: <span
                    class="text-warning fw-bold">Al-Fachrezi Three Aditya</span></div>
        </div>
    </footer>
</body>

</html>