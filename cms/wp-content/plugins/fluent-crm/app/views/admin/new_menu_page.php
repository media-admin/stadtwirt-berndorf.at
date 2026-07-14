<?php
/**
 * @var $base_url string
 * @var $logo string
 * @var $menuItems array
 * @var $proData array
 * @var $renderTopMenuBar bool
 */
?>
<?php do_action('fluent_crm/before_admin_app_wrap'); ?>
<div class="fluentcrm_app_wrapper">
    <?php
    // Extract 'settings' menu (if present) so it's not rendered in the center list
    $fcrm_settings_item = null;
    if (!empty($menuItems) && is_array($menuItems)) {
        foreach ($menuItems as $mi) {
            if (!empty($mi['key']) && $mi['key'] === 'settings') {
                $fcrm_settings_item = $mi;
                break;
            }
        }
    }

    ?>
    <?php if (false !== $renderTopMenuBar) : ?>
    <div class="fcrm_topbar">
        <div class="fcrm_topbar_left">
            <a href="<?php echo esc_url($base_url); ?>">
                <img src="<?php echo esc_url($logo); ?>" alt="FluentCRM Logo" />
                <?php if(defined('FLUENTCAMPAIGN_PLUGIN_PATH')): ?>
                    <span><?php esc_html_e('Pro', 'fluent-crm'); ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="fcrm_topbar_center">
        <ul class="fcrm_menu">
            <li class="fcrm_close_menu_btn_wrap">
                <span class="fcrm_close_menu_btn">
                    <svg class="cross" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15.8337 4.1665L4.16699 15.8332M4.16699 4.1665L15.8337 15.8332" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
            </li>
            <?php foreach ($menuItems as $item): ?>
            <?php
                $hasSubMenu = !empty($item['sub_items']);
                if (!empty($item['key']) && $item['key'] === 'settings') {
                    // skip rendering settings in the center menu
                    continue;
                }
            ?>
            <li data-key="<?php echo esc_attr($item['key']); ?>" class="fcrm_menu_item <?php echo ($hasSubMenu) ? 'fcrm_has_sub_items' : ''; ?> fcrm_item_<?php echo esc_attr($item['key']); ?>">
                <a class="fcrm_menu_primary" href="<?php echo esc_url($item['permalink']); ?>">
                    <?php echo esc_attr($item['label']); ?>
                    <?php if($hasSubMenu){ ?>
                        <span class="fcrm_submenu_handler">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9.99999 10.879L13.7125 7.1665L14.773 8.227L9.99999 13L5.22699 8.227L6.28749 7.1665L9.99999 10.879Z" fill="#99A0AE"/>
                            </svg>
                        </span>
                    <?php } ?></a>
                <?php if($hasSubMenu): ?>

                <?php $layoutClass = \FluentCrm\Framework\Support\Arr::get($item, 'layout_class'); ?>
                <div class="fcrm_submenu_items <?php echo esc_attr(str_replace('fc_', 'fcrm_', (string)$layoutClass)); ?>">
                    <?php foreach ($item['sub_items'] as $sub_item): ?>
                    <a href="<?php echo esc_url($sub_item['permalink']); ?>">
                        <?php
                            if(!$layoutClass) {
                                echo esc_html($sub_item['label']);
                            } else {
                                ?>
                                <div class="fcrm_menu_card <?php if (isset($sub_item['icon'])) { ?>fcrm_menu_card_with_icon<?php } ?>">
                                    <?php if (isset($sub_item['icon'])) { ?>
                                    <span class="fcrm_menu_icon">
                                        <?php echo $sub_item['icon']; ?>
                                    </span>
                                    <?php } ?>
                                    <span class="fcrm_menu_title"><?php echo  esc_html($sub_item['label']); ?></span>
                                    <?php if(!empty($sub_item['description'])): ?>
                                    <p class="fcrm_menu_description"><?php echo wp_kses_post($sub_item['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php
                            }
                        ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        </div>
        <div class="fcrm_topbar_right" aria-label="Topbar actions">
            <div id="fcrm_admin_menu_search"></div>
            <?php if ($fcrm_settings_item): ?>
            <div class="fcrm_icon_menu fcrm_settings_menu" data-key="settings">
                <a href="<?php echo esc_url($fcrm_settings_item['permalink']); ?>" class="fcrm_icon_btn" aria-label="Settings">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M2.5 9.99998C2.5 9.35123 2.5825 8.72273 2.737 8.12198C3.15135 8.14377 3.56365 8.05055 3.92833 7.85264C4.29301 7.65472 4.59586 7.35983 4.8034 7.00054C5.01095 6.64126 5.1151 6.23158 5.10436 5.8168C5.09361 5.40202 4.96837 4.99829 4.7425 4.65023C5.64921 3.75816 6.7681 3.11161 7.99375 2.77148C8.18199 3.14159 8.46898 3.45238 8.82294 3.66947C9.1769 3.88655 9.58402 4.00145 9.99925 4.00145C10.4145 4.00145 10.8216 3.88655 11.1756 3.66947C11.5295 3.45238 11.8165 3.14159 12.0048 2.77148C13.2304 3.11161 14.3493 3.75816 15.256 4.65023C15.0299 4.99835 14.9045 5.40224 14.8936 5.81721C14.8828 6.23218 14.987 6.64206 15.1946 7.00149C15.4023 7.36093 15.7054 7.65591 16.0703 7.8538C16.4352 8.05168 16.8477 8.14476 17.2623 8.12273C17.4167 8.72273 17.4993 9.35123 17.4993 9.99998C17.4993 10.6487 17.4167 11.2772 17.2623 11.878C16.8478 11.8561 16.4354 11.9492 16.0706 12.147C15.7059 12.3449 15.4029 12.6398 15.1953 12.9991C14.9876 13.3584 14.8834 13.7681 14.8941 14.183C14.9048 14.5978 15.0301 15.0016 15.256 15.3497C14.3493 16.2418 13.2304 16.8884 12.0048 17.2285C11.8165 16.8584 11.5295 16.5476 11.1756 16.3305C10.8216 16.1134 10.4145 15.9985 9.99925 15.9985C9.58402 15.9985 9.1769 16.1134 8.82294 16.3305C8.46898 16.5476 8.18199 16.8584 7.99375 17.2285C6.7681 16.8884 5.64921 16.2418 4.7425 15.3497C4.96863 15.0016 5.09405 14.5977 5.10488 14.1828C5.11571 13.7678 5.01152 13.3579 4.80386 12.9985C4.59619 12.639 4.29314 12.3441 3.92823 12.1462C3.56332 11.9483 3.15078 11.8552 2.73625 11.8772C2.5825 11.278 2.5 10.6495 2.5 9.99998ZM6.103 12.25C6.5755 13.0682 6.7105 14.0095 6.526 14.893C6.832 15.1105 7.1575 15.2987 7.49875 15.4555C8.18625 14.8396 9.07699 14.4993 10 14.5C10.945 14.5 11.8285 14.8532 12.5013 15.4555C12.8425 15.2987 13.168 15.1105 13.474 14.893C13.2846 13.99 13.4352 13.0488 13.897 12.25C14.358 11.4508 15.0978 10.8499 15.9745 10.5625C16.0092 10.1883 16.0092 9.81168 15.9745 9.43748C15.0975 9.15028 14.3574 8.54935 13.8962 7.74998C13.4345 6.95118 13.2838 6.01001 13.4733 5.10698C13.1673 4.88943 12.8417 4.7011 12.5005 4.54448C11.8132 5.16018 10.9228 5.50044 10 5.49998C9.07699 5.50063 8.18625 5.16036 7.49875 4.54448C7.1576 4.7011 6.83192 4.88943 6.526 5.10698C6.71542 6.01001 6.56479 6.95118 6.103 7.74998C5.64203 8.5492 4.90224 9.15012 4.0255 9.43748C3.99081 9.81168 3.99081 10.1883 4.0255 10.5625C4.90252 10.8497 5.6426 11.4506 6.10375 12.25H6.103ZM10 12.25C9.40326 12.25 8.83097 12.0129 8.40901 11.591C7.98705 11.169 7.75 10.5967 7.75 9.99998C7.75 9.40325 7.98705 8.83095 8.40901 8.40899C8.83097 7.98704 9.40326 7.74998 10 7.74998C10.5967 7.74998 11.169 7.98704 11.591 8.40899C12.0129 8.83095 12.25 9.40325 12.25 9.99998C12.25 10.5967 12.0129 11.169 11.591 11.591C11.169 12.0129 10.5967 12.25 10 12.25ZM10 10.75C10.1989 10.75 10.3897 10.671 10.5303 10.5303C10.671 10.3897 10.75 10.1989 10.75 9.99998C10.75 9.80107 10.671 9.61031 10.5303 9.46965C10.3897 9.329 10.1989 9.24998 10 9.24998C9.80109 9.24998 9.61032 9.329 9.46967 9.46965C9.32902 9.61031 9.25 9.80107 9.25 9.99998C9.25 10.1989 9.32902 10.3897 9.46967 10.5303C9.61032 10.671 9.80109 10.75 10 10.75Z" fill="#0E121B"/>
                    </svg>
                </a>
            </div>
            <?php endif; ?>
            <div id="fcrm_theme"></div>
            <?php if (!empty($frontendPortalSettings['user_avatar'])): ?>
            <div class="fcrm_portal_profile_menu">
                <button type="button" class="fcrm_portal_profile_button" aria-label="<?php esc_attr_e('Open profile menu', 'fluent-crm'); ?>">
                    <span class="fcrm_portal_profile_avatar"><?php echo $frontendPortalSettings['user_avatar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <svg class="fcrm_portal_profile_chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="fcrm_portal_profile_dropdown">
                    <div class="fcrm_portal_profile_heading">
                        <span class="fcrm_portal_profile_avatar"><?php echo $frontendPortalSettings['user_avatar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <div class="fcrm_portal_profile_identity">
                            <p class="fcrm_portal_profile_name"><?php echo esc_html($frontendPortalSettings['user_name']); ?></p>
                            <p class="fcrm_portal_profile_email"><?php echo esc_html($frontendPortalSettings['user_email']); ?></p>
                        </div>
                    </div>
                    <ul class="fcrm_portal_profile_actions">
                        <?php if (!empty($frontendPortalSettings['show_wp_admin_link']) && !empty($frontendPortalSettings['wp_admin_url'])): ?>
                        <li>
                            <a id="fcrm_wp_admin_link" href="<?php echo esc_url($frontendPortalSettings['wp_admin_url']); ?>">
                                <img src="<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>" alt="<?php esc_attr_e('WordPress', 'fluent-crm'); ?>" />
                                <span><?php esc_html_e('WP Admin', 'fluent-crm'); ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($frontendPortalSettings['logout_url'])): ?>
                        <li>
                            <a href="<?php echo esc_url($frontendPortalSettings['logout_url']); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M10 17L15 12L10 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M15 12H4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 4H18C18.5523 4 19 4.44772 19 5V19C19 19.5523 18.5523 20 18 20H12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Log out', 'fluent-crm'); ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            <div class="fcrm_handheld fcrm_humberg_menu">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <?php if (!$proData['has_pro']) : ?>
                <a
                    class="el-button el-button--primary el-button--small"
                    href="<?php echo esc_url($proData['permalink']); ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <?php echo esc_attr($proData['label']); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var MOBILE_MENU_BREAKPOINT = 1100;
        var toggler = document.querySelector('.fcrm_handheld');
        var menu = document.querySelector('ul.fcrm_menu');
        var closeMenuBtn = document.querySelector('.fcrm_close_menu_btn');
        var settingsWrap = document.querySelector('.fcrm_settings_menu');
        var profileWrap = document.querySelector('.fcrm_portal_profile_menu');
        var profileButton = profileWrap ? profileWrap.querySelector('.fcrm_portal_profile_button') : null;
        
        if (toggler && menu) {
            // Toggle mobile menu
            toggler.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('fcrm_menu_open');
            });

            if (closeMenuBtn) {
                closeMenuBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menu.classList.remove('fcrm_menu_open');
                });
            }
            
            // Handle submenu toggles on mobile
            var menuItems = menu.querySelectorAll('.fcrm_has_sub_items');
            menuItems.forEach(function(item) {
                var primaryLink = item.querySelector('.fcrm_menu_primary');
                if (primaryLink) {
                    primaryLink.addEventListener('click', function(e) {
                        // Only prevent default and toggle on mobile
                        if (window.innerWidth <= MOBILE_MENU_BREAKPOINT) {
                            e.preventDefault();
                            e.stopPropagation();
                            item.classList.toggle('fcrm_submenu_open');
                            
                            // Close other open submenus
                            menuItems.forEach(function(otherItem) {
                                if (otherItem !== item && otherItem.classList.contains('fcrm_submenu_open')) {
                                    otherItem.classList.remove('fcrm_submenu_open');
                                }
                            });
                        }
                    });
                }

                // Desktop: close hovered submenu immediately after parent/child click.
                var links = item.querySelectorAll('a');
                links.forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= MOBILE_MENU_BREAKPOINT) {
                            return;
                        }
                        menu.querySelectorAll('.fcrm_submenu_items.fcrm_force_hide').forEach(function(submenu) {
                            submenu.classList.remove('fcrm_force_hide');
                        });
                        var currentSubmenu = item.querySelector('.fcrm_submenu_items');
                        if (currentSubmenu) {
                            currentSubmenu.classList.add('fcrm_force_hide');
                        }
                    });
                });

                // Re-enable hover behavior once the cursor leaves the menu item.
                item.addEventListener('mouseleave', function() {
                    var currentSubmenu = item.querySelector('.fcrm_submenu_items');
                    if (currentSubmenu) {
                        currentSubmenu.classList.remove('fcrm_force_hide');
                    }
                });
            });

            // Prevent stale forced-hide state after viewport resize.
            window.addEventListener('resize', function() {
                menu.querySelectorAll('.fcrm_submenu_items.fcrm_force_hide').forEach(function(submenu) {
                    submenu.classList.remove('fcrm_force_hide');
                });
            });
        }
        
        if (settingsWrap) {
            // Toggle on click for touch devices, but allow anchor navigation
            settingsWrap.addEventListener('click', function(e){
                if (e.target.closest('a')) {
                    return; // let the link navigate normally
                }
                e.stopPropagation();
                settingsWrap.classList.toggle('fcrm_open');
            });
        }

        if (profileWrap && profileButton) {
            profileButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                profileWrap.classList.toggle('is-open');
            });
        }

        var wpAdminLink = document.getElementById('fcrm_wp_admin_link');
        if (wpAdminLink) {
            var wpAdminBaseUrl = wpAdminLink.getAttribute('href').split('#')[0];
            var syncWpAdminLink = function() {
                var hash = window.location.hash || '#/';
                wpAdminLink.setAttribute('href', wpAdminBaseUrl + hash);
            };

            syncWpAdminLink();
            window.addEventListener('hashchange', syncWpAdminLink);
        }
        
        // Consolidated document click handler for closing menus
        document.addEventListener('click', function(e) {
            // Close mobile menu if open
            if (menu && menu.classList.contains('fcrm_menu_open') && 
                !menu.contains(e.target) && 
                !toggler.contains(e.target)) {
                menu.classList.remove('fcrm_menu_open');
                // Close all submenus
                var openSubmenus = menu.querySelectorAll('.fcrm_submenu_open');
                openSubmenus.forEach(function(item) {
                    item.classList.remove('fcrm_submenu_open');
                });
            }
            
            // Close settings menu if open
            if (settingsWrap && settingsWrap.classList.contains('fcrm_open')) {
                settingsWrap.classList.remove('fcrm_open');
            }

            if (profileWrap && profileWrap.classList.contains('is-open') && !profileWrap.contains(e.target)) {
                profileWrap.classList.remove('is-open');
            }
        });
    });
    </script>
    <?php else : ?>
    <div id="fcrm_admin_menu_search" style="display: none;"></div>
    <div id="fcrm_theme" style="display: none;"></div>
    <?php endif; ?>
    <div id='fluentcrm_app'></div>
    <?php do_action('fluent_crm/admin_app'); ?>
</div>
