<?php
/**
 * Plugin Name: Cualli - Link Monitor
 * Description: Escanea cada semana los enlaces internos, imágenes, CSS y JS del sitio y envía un correo si encuentra recursos rotos.
 * Version:     1.0.0
 * Author:      Cualli
 * Requires PHP: 8.2
 *
 * Loader of the mu-plugin. WordPress only loads the PHP files at the root of
 * `mu-plugins`, so the code lives in `cualli-link-monitor/` and this file only
 * registers the autoloader and the hooks.
 *
 * @package Cualli\LinkMonitor
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/cualli-link-monitor/src/Autoloader.php';

\Cualli\LinkMonitor\Autoloader::register( __DIR__ . '/cualli-link-monitor/src' );

( new \Cualli\LinkMonitor\Plugin( new \Cualli\LinkMonitor\ServiceFactory() ) )->register_hooks();
