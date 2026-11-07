<?php get_header(); ?>

<main class="j3-site-main">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php if ( ! is_front_page() ) : ?>
            <section class="j3-page-header">
                <div class="j3-container">
                    <h1><?php the_title(); ?></h1>
                </div>
            </section>
            <div class="j3-container" style="padding-top:48px;padding-bottom:64px;">
                <?php the_content(); ?>
            </div>
        <?php else : ?>
            <?php the_content(); ?>
        <?php endif; ?>
    <?php endwhile; ?>
</main>

<footer class="j3-footer">
    <div class="j3-container">
        <p style="color:var(--j3-yellow);font-family:var(--j3-font-display);font-size:32px;letter-spacing:1.5px;margin:0 0 8px;line-height:1;">
            03<span style="color:#ffffff;">.</span>07 // <?php esc_html_e( '101 DEMANDS GLOBAL MOBILIZATION', '101demands-theme' ); ?>
        </p>
        <p style="color:#d4d4d4;margin:0;">Hosted in Iceland on 1984 Hosting • 100% Free &amp; Open-Source Software (FOSS) • Zero Tracking Cookies</p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>