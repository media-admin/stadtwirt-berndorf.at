<?php
/**
 * Template Name: OLD Agency Homepage
 *
 * @package CustomTheme
 */

get_header();
?>

<main id="primary" class="site-main">
    
    <?php
    // Hero Slider
    get_template_part('template-parts/components/hero-slider', null, array(
        'slides' => array(
            array(
                'image' => 'https://picsum.photos/1920/1080?random=1',
                'title' => 'Willkommen bei unserer Agency',
                'subtitle' => 'Wir kreieren digitale Erlebnisse',
                'button_text' => 'Mehr erfahren',
                'button_link' => '#features',
            ),
            array(
                'image' => 'https://picsum.photos/1920/1080?random=2',
                'title' => 'Innovation & Kreativität',
                'subtitle' => 'Ihre Vision, unsere Expertise',
                'button_text' => 'Projekt starten',
                'button_link' => '/kontakt',
            ),
        )
    ));
    ?>
    
    <!-- Features mit Animationen -->
    <section id="features" class="section-padding">
        <div class="container">
            <header class="section-header" data-animate="fade-in-up">
                <h2>Unsere Leistungen</h2>
                <p>Was wir für Sie tun können</p>
            </header>
            
            <div class="card-grid" data-animate-stagger>
                <?php for ($i = 1; $i <= 6; $i++) : ?>
                    <div class="card">
                        <img src="https://picsum.photos/400/300?random=<?php echo $i; ?>" alt="Feature <?php echo $i; ?>">
                        <div class="card__content">
                            <h3 class="card__title">Feature <?php echo $i; ?></h3>
                            <p class="card__description">Lorem ipsum dolor sit amet consectetur.</p>
                            <a href="#" class="card__link">Mehr erfahren →</a>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    
    <!-- Accordion FAQ -->
    <section class="section-padding bg-light">
        <div class="container">
            <header class="section-header" data-animate="fade-in">
                <h2>Häufige Fragen</h2>
            </header>
            
            <?php
            get_template_part('template-parts/components/accordion', null, array(
                'items' => array(
                    array(
                        'title' => 'Was kostet ein Projekt?',
                        'content' => 'Die Kosten variieren je nach Umfang. Kontaktieren Sie uns für ein individuelles Angebot.',
                    ),
                    array(
                        'title' => 'Wie lange dauert die Umsetzung?',
                        'content' => 'Ein typisches Projekt dauert 4-8 Wochen, abhängig von der Komplexität.',
                    ),
                    array(
                        'title' => 'Bieten Sie auch Support an?',
                        'content' => 'Ja, wir bieten verschiedene Support-Pakete für die Zeit nach dem Launch an.',
                    ),
                )
            ));
            ?>
        </div>
    </section>
    
    <!-- Lightbox Gallery -->
    <section class="section-padding">
        <div class="container">
            <header class="section-header" data-animate="fade-in">
                <h2>Portfolio</h2>
            </header>
            
            <div class="card-grid">
                <?php for ($i = 1; $i <= 9; $i++) : ?>
                    <a href="https://picsum.photos/1200/800?random=<?php echo $i + 10; ?>" 
                       data-lightbox="portfolio" 
                       data-caption="Portfolio Projekt <?php echo $i; ?>">
                        <img src="https://picsum.photos/400/300?random=<?php echo $i + 10; ?>" 
                             alt="Portfolio <?php echo $i; ?>"
                             loading="lazy">
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    
    <!-- Modal Trigger -->
    <section class="section-padding bg-primary text-center">
        <div class="container">
            <h2 style="color: white;">Bereit loszulegen?</h2>
            <p style="color: white; opacity: 0.9;">Sprechen Sie uns an für ein unverbindliches Gespräch</p>
            <button class="btn btn-outline" data-modal-trigger="contact-modal">
                Kontakt aufnehmen
            </button>
        </div>
    </section>
    
</main>

<?php
// Contact Modal
get_template_part('template-parts/components/modal', null, array(
    'id' => 'contact-modal',
    'title' => 'Kontaktieren Sie uns',
    'content' => '
        <form>
            <div class="form-group">
                <label>Name</label>
                <input type="text" class="form-control" required>
            </div>
            <div class="form-group">
                <label>E-Mail</label>
                <input type="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Nachricht</label>
                <textarea class="form-control" rows="4" required></textarea>
            </div>
        </form>
    ',
    'footer' => '
        <button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button>
        <button type="submit" class="btn btn-primary">Senden</button>
    '
));

get_footer();