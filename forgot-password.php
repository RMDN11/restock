<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/mailer.php';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$message='Link reset password sudah dikirim. Silakan cek email kamu, termasuk folder Spam.';
$error='';
$flashSuccess=$_SESSION['flash_success']??'';
unset($_SESSION['flash_success']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email=trim($_POST['email']??'');
    if ($email==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Masukkan alamat email yang valid.';
    else {
        $stmt=$pdo->prepare("SELECT id,name,email,status FROM users WHERE email=:email LIMIT 1");
        $stmt->execute([':email'=>$email]); $user=$stmt->fetch();
        if ($user && $user['status']==='ACTIVE') {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE password_resets SET used_at=CURRENT_TIMESTAMP WHERE user_id=:user_id AND used_at IS NULL")->execute([':user_id'=>$user['id']]);
                $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token);
                $stmt=$pdo->prepare("INSERT INTO password_resets (user_id,token_hash,expires_at,created_at) VALUES (:user_id,:token_hash,DATE_ADD(NOW(),INTERVAL 30 MINUTE),CURRENT_TIMESTAMP)");
                $stmt->execute([':user_id'=>$user['id'],':token_hash'=>$hash]);
                $link='https://restock.reqra.my.id/reset-password.php?token='.rawurlencode($token);
                try {
                    sendPasswordResetEmail($user['email'], $user['name'], $link);
                    $pdo->commit();
                    $_SESSION['flash_success']=$message;
                } catch (Throwable $mailError) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('RESTOCK password reset email failed: '.$mailError->getMessage());
                    $_SESSION['flash_error']='Email reset gagal dikirim. Silakan coba lagi.';
                }
            } catch (Throwable $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                error_log('RESTOCK password reset failed: '.$e->getMessage());
                $_SESSION['flash_error']='Proses reset password gagal. Silakan coba lagi.';
            }
        }
        if (!$flashSuccess && empty($_SESSION['flash_success']) && empty($_SESSION['flash_error'])) {
            $_SESSION['flash_success']=$message;
        }
        header('Location: /forgot-password.php'); exit;
    }
}
$flashError=$_SESSION['flash_error']??'';
unset($_SESSION['flash_error']);
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lupa Password</title><link rel="stylesheet" href="/assets/css/app.css"><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-neutral-50 flex items-center justify-center p-4"><div class="w-full max-w-md"><div class="bg-white rounded-3xl border border-neutral-200 p-6 md:p-8 shadow-sm"><div class="mb-7"><div class="text-xs tracking-[.2em] text-neutral-400 font-semibold">RE-STOCK</div><h1 class="text-2xl font-semibold mt-2">Lupa Password?</h1><p class="text-sm text-neutral-500 mt-2">Masukkan email akun untuk menerima tautan pemulihan.</p></div><?php if($flashSuccess):?><div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"><?=e($flashSuccess)?></div><?php endif;?><?php if($flashError):?><div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?=e($flashError)?></div><?php endif;?><?php if($error):?><div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?=e($error)?></div><?php endif;?><form method="post" class="space-y-5"><div><label for="email" class="block text-sm font-medium text-neutral-700 mb-2">Email</label><input type="email" id="email" name="email" required autocomplete="email" class="w-full px-4 py-3 rounded-xl border border-neutral-200 outline-none focus:border-neutral-500" placeholder="nama@email.com"></div><button class="w-full rounded-xl bg-neutral-900 text-white py-3 font-medium">Kirim Tautan Reset</button></form><a href="/login.php" class="block text-center mt-5 text-sm text-neutral-500 hover:text-neutral-900">Kembali ke login</a></div></div></body></html>
