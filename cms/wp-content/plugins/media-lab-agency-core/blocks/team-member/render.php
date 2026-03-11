<?php
/**
 * Team-Mitglied Block – ACF Render Template
 *
 * ACF-Felder:
 *   team_image          Image    Porträtfoto
 *   team_name           Text     Vollständiger Name
 *   team_role           Text     Berufsbezeichnung / Rolle
 *   team_bio            Textarea Kurze Beschreibung (optional)
 *   team_email          Email    E-Mail-Adresse (optional)
 *   team_linkedin       URL      LinkedIn-Profil (optional)
 *   team_xing           URL      Xing-Profil (optional)
 *   team_instagram      URL      Instagram-Profil (optional)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$image    = get_field( 'team_image' );
$name     = get_field( 'team_name' );
$role     = get_field( 'team_role' );
$bio      = get_field( 'team_bio' );
$email    = get_field( 'team_email' );
$linkedin = get_field( 'team_linkedin' );
$xing     = get_field( 'team_xing' );
$instagram= get_field( 'team_instagram' );

$block_classes = 'ml-block-team-member';
if ( ! empty( $block['className'] ) ) $block_classes .= ' ' . $block['className'];

$image_url = is_array( $image )
    ? ( $image['sizes']['medium'] ?? $image['url'] ?? '' )
    : (string) $image;
$image_alt = is_array( $image ) ? ( $image['alt'] ?? $name ) : $name;

$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

$social_links = array_filter( [
    'email'     => $email    ? 'mailto:' . antispambot( $email ) : '',
    'linkedin'  => $linkedin ?: '',
    'xing'      => $xing     ?: '',
    'instagram' => $instagram ?: '',
] );

?>
<div class="<?php echo esc_attr( $block_classes ); ?>"<?php echo $block_id; ?>>

    <?php if ( $image_url ) : ?>
    <div class="ml-team-member__image-wrap">
        <img src="<?php echo esc_url( $image_url ); ?>"
             alt="<?php echo esc_attr( $image_alt ); ?>"
             class="ml-team-member__image"
             width="320" height="320"
             loading="lazy">
    </div>
    <?php endif; ?>

    <div class="ml-team-member__body">
        <?php if ( $name ) : ?>
        <h3 class="ml-team-member__name"><?php echo esc_html( $name ); ?></h3>
        <?php endif; ?>

        <?php if ( $role ) : ?>
        <p class="ml-team-member__role"><?php echo esc_html( $role ); ?></p>
        <?php endif; ?>

        <?php if ( $bio ) : ?>
        <p class="ml-team-member__bio"><?php echo wp_kses_post( $bio ); ?></p>
        <?php endif; ?>

        <?php if ( $social_links ) : ?>
        <ul class="ml-team-member__social" aria-label="<?php echo esc_attr( $name ); ?> Social Links">
            <?php foreach ( $social_links as $platform => $url ) : ?>
            <li>
                <a href="<?php echo esc_url( $url ); ?>"
                   class="ml-team-member__social-link ml-team-member__social-link--<?php echo esc_attr( $platform ); ?>"
                   <?php echo $platform !== 'email' ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                   aria-label="<?php echo esc_attr( ucfirst( $platform ) ); ?>">
                    <span class="screen-reader-text"><?php echo esc_html( ucfirst( $platform ) ); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

</div>
