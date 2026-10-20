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
        <p style="color:#fff;font-family:'Anton',sans-serif;font-size:22px;letter-spacing:1px;margin-bottom:8px;">101 DEMANDS // JULY 3 GLOBAL MOBILIZATION</p>
        <p>Hosted in Iceland on 1984 Hosting • 100% Free &amp; Open-Source Software (FOSS) • Zero Tracking Cookies</p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>