<?php

/**
 * ACF Blocks Loader
 * ACF v6.0 ready - searching for and loading 'block.json' files
 */

define('TEMPLATE_PATH', get_template_directory() . '/');
define('TEMPLATE_URI', get_template_directory_uri() . '/');
define('BLOCK_ASSETS', 'assets/');
define('BLOCK_STYLES', BLOCK_ASSETS . 'css/');
define('BLOCK_SCRIPTS', BLOCK_ASSETS . 'js/');

// main block & parts paths
define('ACF_BLOCKS', 'blocks/');

// default block category
define('DEFAULT_BLOCK_CATEGORY_TITLE', __('ADS'));
define('DEFAULT_BLOCK_CATEGORY_SLUG', 'ads');
define('DEFAULT_BLOCK_CLASS', 'ads-blocks');


function register_acf_block_types()
{

	// transcient data
	if (false === ($blocks = get_transient('acf_blocks_to_load'))) {

		//echo "transcient data expired or not exist, creating...";

		try {
			$directory = new \RecursiveDirectoryIterator(TEMPLATE_PATH . ACF_BLOCKS, \FilesystemIterator::FOLLOW_SYMLINKS);
		} catch (Exception $e) {

			// echo error
			echo $e->getMessage();

			// error log
			error_log($e->getMessage());

			// exit
			return;
		}

		try {
			$filter = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {

				// Skip hidden files and directories.
				if ($current->getFilename()[0] === '.')
					return FALSE;

				// is dir
				if ($current->isDir()) {
					return true;
				} else {
					// get only '*.json' files
					return strpos($current->getFilename(), 'block.json');
				}
			});

			$blocks = array();

			$files = new \RecursiveIteratorIterator($filter);
			foreach ($files as $file) {

				// read the file
				$block_json = file_get_contents($file);

				// decode json
				$block_arr = json_decode($block_json, true);

				// throw error if not valid
				if (!is_array($block_arr)) {
					throw new Exception('Block JSON is not valid! (' . $file . ')');
				}

				// get file name - __DIR__ can be used also
				$block_name = str_replace('.block.json', '', $file->getfileName());

				// add to array
				$blocks[] = wp_normalize_path($file);
			}

			// save transcient
			set_transient('acf_blocks_to_load', $blocks, 10);

			// catch
		} catch (Exception $e) {
			error_log($e->getMessage());
		}
	}

	// register found blocks
	foreach ($blocks as $block) {
		register_block_type($block);
	}
}

/**
 * alter block data after registration
 */
add_filter("block_type_metadata", function ($metadata) {

	// alter only if block name starts with our slug
	if (DEFAULT_BLOCK_CATEGORY_SLUG . '/' === substr($metadata['name'], 0, strlen(DEFAULT_BLOCK_CATEGORY_SLUG . '/'))) {

		// get block name
		$block_name = substr($metadata['name'], strlen(DEFAULT_BLOCK_CATEGORY_SLUG . '/'));

		// generate block basic classes
		$metadata['class'] = DEFAULT_BLOCK_CLASS . ' ' . DEFAULT_BLOCK_CLASS . '__' . $block_name;
	}

	if (str_starts_with($metadata['name'], 'ads/') && !isset($metadata['example'])) {
		$metadata['example'] = array(
			"attributes"    => array(
				"mode"  => "preview",
				"align" => "full",
				"data"  => array(
					"is_preview" => true
				),
			),
			"viewportWidth" => 1500,
		);
	}
	return $metadata;
});

// Check if function exists and hook into setup.
if (function_exists('acf_register_block_type')) {
	add_action('acf/init', 'register_acf_block_types', 5);
}

// Create "tailpress-theme-blocks" for theme-based custom blocks only
function tailpress_theme_blocks_category($categories, $post)
{
	return array_merge(
		$categories,
		array(
			array(
				'slug'  => 'tailpress-theme-blocks',
				'title' => __('Tailpress Theme Blocks', 'tailpress-theme-blocks'),
				'icon'  => 'wordpress',
			),
		)
	);
}
add_filter('block_categories_all', 'tailpress_theme_blocks_category', 10, 2);

// Push our custom block categories to the top of the Blocks panel stack.
function tailpress_register_layout_category($categories, $post)
{


	// TODO remove this when this ordering is fixed in the plugin.
	$custom_categories = array(
		'slug'  => 'tailpress-blocks',
		'title' => 'Tailpress Blocks'
	);

	// Keep this when "Amsive Blocks" is removed from this array unshift.
	$custom_categories2 = array(
		'slug'  => 'tailpress-theme-blocks',
		'title' => 'Tailpress Theme Blocks'
	);

	array_unshift($categories, $custom_categories, $custom_categories2);

	return $categories;
}

add_filter('block_categories_all', 'tailpress_register_layout_category', 10, 2);

function updated_acf_block_render_callback($block, $content = '', $is_preview = false, $post_id = 0)
{
	$preview_go = true;
	$is_pattern = false;

	global $post;
	if ($post) {
		if (isset($post->post_name)) {
			$p_name = $post->post_name;
			if (substr($p_name, 0, 16) === "pattern-preview-") {
				$is_pattern = true;
			}
		}
	}

	if ($is_preview || $is_pattern) {
		if (!empty($block['data'])) {
			foreach ($block['data'] as $k => $v) {
				if (substr($k, 0, 1) != "_") {
					if ($v != '') {
						$preview_go = false;
						break;
					}
				}
			}
		}
		if (isset($block['example']['attributes']['data'])) {
			if (!empty($block['example']['attributes']['data'])) {
				foreach ($block['example']['attributes']['data'] as $k => $v) {
					if (substr($k, 0, 1) != "_") {
						if ($v != '') {
							$preview_go = false;
							break;
						}
					}
				}
			}
		}
	} else {
		$preview_go = false;
	}

	if (substr($block['name'], 0, 4) === "acf/") {
		$block_slug = str_replace('acf/', '', $block['name']);
	} else {
		$block_slug = str_replace('amsive/', '', $block['name']);
	}

	if ($preview_go) {
		echo '<div class="wp-block-cover" >';
		if (isset($block['example']['attributes']['cover'])) {
			echo '<span aria-hidden="true" class="wp-block-cover__background has-background-dim-40 has-background-dim"></span>';
			echo '<img class="wp-block-cover__image-background preview-image" src="' . TEMPLATE_URI . 'blocks/' . $block_slug . '/dist/' . $block['example']['attributes']['cover'] . '"/>';
		}
		echo '<div class="wp-block-cover__inner-container">
                    <p class="has-text-align-center has-large-font-size">block demo preview</p>
                    </div>';
		echo '</div>';
	} else {
		if (file_exists(get_theme_file_path('/blocks/' . $block_slug . '/' . $block_slug . '.render.php'))) {
			include get_theme_file_path('/blocks/' . $block_slug . '/' . $block_slug . '.render.php');
		}
	}
}