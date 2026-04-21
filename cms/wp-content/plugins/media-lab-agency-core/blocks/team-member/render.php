<?php
/**
 * Team-Mitglied Block – ACF Render Template
 *
 * WCAG-Patches:
 *   ✅ 1.3.1 Info and Relationships: aria-hidden auf Dashicon-Pseudoelement-Wrapper
 *   ✅ 2.5.5 Target Size: Touch-Target 36px → 44px via CSS
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0 / WCAG-Patch
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

$platform_labels = [
    'email'     => __( 'E-Mail senden', 'media-lab-agency-core' ),
    'linkedin'  => __( 'LinkedIn-Profil', 'media-lab-agency-core' ),
    'xing'      => __( 'Xing-Profil', 'media-lab-agency-core' ),
    'instagram' => __( 'Instagram-Profil', 'media-lab-agency-core' ),
];

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
        <ul class="ml-team-member__social"
            aria-label="<?php echo esc_attr( $name ); ?> – Social Links">
            <?php foreach ( $social_links as $platform => $url ) :
                $label = sprintf(
                    /* translators: 1: platform label, 2: person name */
                    __( '%1$s von %2$s', 'media-lab-agency-core' ),
                    $platform_labels[ $platform ] ?? ucfirst( $platform ),
                    $name
                );
            ?>
            <li>
                <a href="<?php echo esc_url( $url ); ?>"
                   class="ml-team-member__social-link ml-team-member__social-link--<?php echo esc_attr( $platform ); ?>"
                   <?php echo $platform !== 'email' ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                   aria-label="<?php echo esc_attr( $label ); ?>">
                    
                    <!-- ✅ WCAG 1.3.1: Icon-Span explizit vor AT verbergen -->
                    <span class="ml-team-member__social-icon" aria-hidden="true"></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

</div>
