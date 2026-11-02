</main>
<?php if (!empty($showSidebar)): ?></div></div><?php endif; ?>
<footer class="footer mt-5">
    <div class="container">
        <div class="footer-main">
            <div><a class="footer-brand" href="<?= e(base_url()) ?>"><span class="brand-mark"><i class="fa-solid fa-shapes"></i></span><?= e($schoolName ?? 'PPDB Sekolah') ?></a><p class="footer-manifesto">Curious minds.<br><span>Brighter tomorrows.</span></p></div>
            <div class="footer-links"><a href="<?= e(base_url()) ?>#profil">Tentang sekolah</a><a href="<?= e(base_url()) ?>#jenjang">Jenjang</a><a href="<?= e(base_url()) ?>#informasi">Informasi PPDB</a><a href="<?= e(base_url()) ?>#faq">FAQ</a></div>
            <div class="footer-contact"><span class="eyebrow">Say hello</span><?php if (!empty($schoolEmail)): ?><a href="mailto:<?= e($schoolEmail) ?>"><?= e($schoolEmail) ?></a><?php endif; ?><?php if (!empty($schoolPhone)): ?><a href="tel:<?= e($schoolPhone) ?>"><?= e($schoolPhone) ?></a><?php endif; ?><a href="<?= e(base_url()) ?>#kontak">Kontak panitia <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a></div>
        </div>
        <div class="footer-bottom"><span>&copy; <?= date('Y') ?> <?= e($schoolName ?? 'PPDB Sekolah') ?> · Dibuat untuk masa depan.</span><span>PPDB · OPEN A NEW CHAPTER</span></div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
