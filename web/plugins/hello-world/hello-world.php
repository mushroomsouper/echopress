<?php
/**
 * Plugin Name: Hello World
 * Description: Sample EchoPress plugin that adds a Docs link to the nav.
 * Version: 0.1.0
 * Author: EchoPress
 */

if (!function_exists('add_filter')) return;

add_filter('nav_links', function (array $links) {
    $links[] = [
        'label' => 'Docs',
        'href' => 'https://example.com/docs',
        'active' => false,
    ];
    return $links;
}, 20);

