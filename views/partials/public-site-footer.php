<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="footer-top">
            <a class="brand brand--footer" href="<?php echo e($publicHomeUrl); ?>" aria-label="<?php echo e($siteName); ?> home">
                <span class="brand__mark" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="M24 4H6v26.6667h18V44h18V17.3333H24V4Z" fill="currentColor"/></svg></span>
                <span class="brand__name"><?php echo e($siteName); ?></span>
            </a>
            <p>One clear place for the work ahead.</p>
            <div class="site-footer__links" aria-label="Footer navigation">
                <a href="<?php echo e($publicHowItWorksUrl); ?>">How it works</a>
                <a href="<?php echo e($publicMobileUrl); ?>">Mobile</a>
                <a href="<?php echo e($publicPricingUrl); ?>">Pricing</a>
                <a href="<?php echo e($publicDocsUrl); ?>">Documentation</a>
                <?php if ($hasApkRelease): ?><a href="<?php echo e($apkDownloadUrl); ?>" download>Android download</a><?php endif; ?>
                <a href="<?php echo e($sourceDownloadUrl); ?>">Download source (.zip)</a>
                <a href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">Open source on GitHub <span aria-hidden="true">↗</span></a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?php echo date('Y'); ?> <?php echo e($siteName); ?></span>
            <a href="#top">Back to top ↑</a>
        </div>
    </div>
</footer>
