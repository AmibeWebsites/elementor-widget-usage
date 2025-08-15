<?php
/**
 * Elementor Widgets in Use
 *
 * @package           MU
 * @author            Amibe <info@amibe.net>
 *
 * @wordpress-plugin
 * Plugin Name: Elementor Widgets in Use
 * Description: Adds a Widget Usage tab to Elementor Tools in the WordPress admin showing which widget elements are used and where.
 * Version: 1.0.1
 * Author: Amibe <info@amibe.net>
 * Author URI: https://amibe.net
 * Text Domain: element-usage
 */

namespace Amibe\MU;

if (!defined('WPINC')) {
    die;
}

use Elementor\{Plugin, Tools};

add_action('init', function() {
    elementUsage::init();
});

/**
 * @package    MU
 * @author     Amibe <info@amibe.net>
 */
class elementUsage
{
    protected static function instance()
    {
        static $instance = false;
        if (!$instance) {
            $instance = new self;
        }

        return $instance;
    }

    public static function init()
    {
        // Checks
        if (
            !is_admin()
            || !current_user_can('manage_options')
            || !is_plugin_active('elementor/elementor.php')
        ) {
            return false;
        }
        
        add_action('elementor/admin/after_create_settings/elementor-tools', [self::instance(), 'addWidgetsTab']);
    }
    
    public static function addWidgetsTab(Tools $tools)
    {
        $tools->add_tab(
            'widget-usage',
            [
                'label' => __('Widget Usage', 'element-usage'),
                'sections' => [
                    'widget-usage-list' => [
                        'callback'  => [self::instance(), 'displayWidgets'],
                        'fields'    => [],
                    ],
                ],
            ]
        );
    }
    
    public function displayWidgets()
    {
        $widget_usage = self::getWidgetUsage();
        
        if (empty($widget_usage)) {
            return false;
        }
        
        self::theCSS();
        ?>
	<h2><?php _e('Widget Usage', 'element-usage'); ?></h2>
        <div class="widget head">
            <span><?php _e('Widget', 'element-usage') ?></span>
            <span><?php _e('Page/post', 'element-usage') ?></span>
        </div>
        <?php
        foreach ($widget_usage as $widget_name => $widget_posts):
            $widget = Plugin::$instance->widgets_manager->get_widget_types($widget_name);
            ?>
            <div class="widget">
                <p><?php echo !empty($widget) ? $widget->get_title() : "<em>$widget_name</em>"; ?></p>
                <?php self::displayPostsUsingWidget($widget_name, $widget_posts); ?>
            </div>
            <?php
        endforeach;
    }
    
    /**
     * Displays posts/pages/templates using Elementor widgets
     * 
     * @param string $column_name The name of the column
     * @param integer $post_id The current post ID
     */
    protected function displayPostsUsingWidget(string $widget = null, array $widget_posts = [])
    {
        if (empty($widget_posts)) {
            return false;
        }
        
        echo "<ul class=\"widget-usage\">";

        foreach ($widget_posts as $post) {
            ?>
            <li>
                <button type="button" data-tooltip aria-label="Tooltip" class="amibe-tooltip">
                    <span role="tooltip">
                        <strong><?php _e('Post type', 'element-usage'); ?>:</strong> <?php echo $post['post-type']; ?><br>
                        <strong><?php _e('Status', 'element-usage'); ?>:</strong> <?php echo $post['post-status']; ?>
                    </span>
                </button>
                <?php if ($post['edit-link']): ?>
                    <a href="<?php echo $post['edit-link']; ?>"><?php echo $post['post-title']; ?></a> x<?php echo $post['count']; ?>
                <?php else: ?>
                    <?php echo $post['post-title']; ?> x<?php echo $post['count']; ?>
                <?php endif; ?>
            </li>
            <?php
        }

        echo '</ul>';
    }
    
    /**
     * Holds and returns a mapping of widgets used in pages, posts,
     * custom post types and or templates
     * 
     * @staticvar array $widgetUsage List of widgets used and the posts that use them
     * @return array List of widgets used and the posts that use them
     */
    protected function getWidgetUsage()
    {
        /**
         * Holds where templates are used
         * @var array 
         */
        static $widget_usage = [];

        if (!empty($widget_usage)) {
            return $widget_usage;
        }
        
        // Build query of all 'posts' with template references
        $args = [
            'nopaging'      => true,
            'post_type'     => ['any','elementor_library'],
            'post_status'   => 'any',
            'perm'          => 'readable',
        ];
        $args['meta_query'] = [
            [
                'key'       => '_elementor_data',
                'value'     => '"elType":"widget"',
                'compare'   => 'LIKE',
            ],
        ];

        // Run the query
        $query = new \WP_Query($args);

        // Initialise to hold post type data
        $post_types = [];

        // Make sure we have a list of post types and statuses
        $post_statuses = get_post_statuses();
        
        //There is a query so loop
        if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();

            $post = get_post();
            
            // Get the post type
            $post_types[$post->post_type] = empty($post_types[$post->post_type]) ? get_post_type_object($post->post_type) : $post_types[$post->post_type];
            $post_type = $post->post_type === 'elementor_library' ? 'Template' : $post_types[$post->post_type]->labels->singular_name;
                   
            // Get the post title
            $post_title = $post->post_status === 'publish' ? $post->post_title : "<em>{$post->post_title}</em>";
            
            // Get the post status
            $post_status = $post_statuses[$post->post_status];
            
            // Get the edit link
            $edit_link = false;
            $document = Plugin::$instance->documents->get(get_the_ID());
            if ($document && $document->is_built_with_elementor() && $document->is_editable_by_current_user()) {
                $edit_link = $document->get_edit_url();
            } elseif (current_user_can('edit_post', get_the_ID())) {
                $edit_link = get_edit_post_link();
            }
                
            // Get the post's widgets
            $elements = !empty($post->_elementor_data) && is_string($post->_elementor_data) ? json_decode($post->_elementor_data, true) : [];
            $post_widgets = self::findWidgets($elements);

            foreach ($post_widgets as $widget => $count) {
                $widget_usage[$widget][get_the_ID()] = [
                    'count' => $count,
                    'post-type' => $post_type,
                    'post-title' => $post_title,
                    'post-status'   => $post_status,
                    'edit-link' => $edit_link,
                ];
            }
        endwhile; endif;

        wp_reset_postdata();
        
        return !empty($widget_usage) ? $widget_usage : [];
    }
    
    /**
     * Creates a list of widgets with a count of each occurrence
     * 
     * @param array $elements Elements in a hierarchy
     * @param array $widgets an existing list of widgets found with a count of each occurrence
     * @return array A list of widgets (key) with a count of each occurrence (value)
     */
    protected function findWidgets(array $elements, array $widgets = [])
    {
        foreach ($elements as $element) {
            
            // Check for element type of widget and add to $widgets
            if (!empty($element['elType']) && $element['elType'] === 'widget'){
                
                // Initialise array entry with count of 0
                if (empty($widgets[$element['widgetType']])) {
                    $widgets[$element['widgetType']] = 0;
                }
                
                // Increment count
                $widgets[$element['widgetType']]++;
            }

            // Check for sub-elements and iterate
            if (!empty($element['elements'])) {
                $widgets = self::findWidgets($element['elements'], $widgets);
            }
        }

        return $widgets;
    }
    
    protected function theCSS() {
        ?>
        <style>
            .widget {
                display: flex;
                align-items: baseline;
            }
            .widget > * {
                width: 20%;
            }
            .widget-usage{
                margin: 0;
            }
            .widget-usage li{
                position: relative;
            }
            .widget.head {
                font-weight: bold;
            }
            /* Tooltip styling */
            .amibe-tooltip[data-tooltip] {
                position: absolute;
                left: -1.3em;
                top: 0.3em;
                border: unset;
                background: unset;
                padding: 0;
                font-family: inherit;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            @media screen and (-ms-high-contrast: active) {
                .amibe-tooltip[data-tooltip] {
                    border: 2px solid currentcolor;
                }
            }
            .amibe-tooltip[data-tooltip]::before {
                content: '?';
                color: #fff;
                background-color: #00000022;
                border-radius: 50%;
                width: 1.3em;
                height: 1.3em;
                font-size: 0.8em;
                font-weight: bold;
                line-height: 1.3;
                display: inline-block;
            }
            .amibe-tooltip[data-tooltip] span {
                font-size: 0.9em;
                letter-spacing: 0.01em;
                text-align: left;
                position:absolute;
                z-index: 9999;
                background:#000;
                color:#e0e0e0;
                padding:0.5em 1em 0.7em;
                line-height: 1.5;
                min-width:200px;
                top:50%;
                right:100%;
                margin-right:0.7em;
                transform:translate(0, -50%);
                border-radius:3px;
                box-shadow:0 1px 8px rgba(0,0,0,0.5);
                display: none;
                opacity: 0; 
                transition:opacity 0.4s ease-out; 
            }
            .amibe-tooltip[data-tooltip] span::after {
                content:'';
                position:absolute;
                width:1em;
                height:1em;
                right:-1em;
                top:50%;
                transform:translate(-50%,-50%) rotate(-45deg);
                background-color:#000;
                box-shadow:0 1px 8px rgba(0,0,0,0.5);
            }
            .amibe-tooltip[data-tooltip]:hover:before, .amibe-tooltip[data-tooltip]:focus:before {
                background-color: #00;
            }
            .amibe-tooltip[data-tooltip]:hover span, .amibe-tooltip[data-tooltip]:focus span {
                display: block;
                opacity: 1;
            }
            @media screen and (max-width: 782px){
                .wp-list-table .is-expanded td:not(.hidden) {
                    overflow: inherit;
                }
                .amibe-tooltip[data-tooltip] span {
                    top: unset;
                    bottom: 100%;
                    transform: unset;
                    right: unset;
                    margin-right: 0;
                    margin-bottom: 0.7em;
                }
                .amibe-tooltip[data-tooltip] span::after {
                    right: 0;
                    bottom: -1em;
                    top: unset;
                    left: 50%;
                }
            }
        </style>
        <?php
    }
}
