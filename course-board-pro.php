<?php
/**
 * Plugin Name: Course Board Pro
 * Description: Create isolated course boards with dynamic, project-specific filters and meta fields.
 * Version: 3.4
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// 1. Register the Custom Post Type and Taxonomies
add_action( 'init', 'cbp_register_architecture' );
function cbp_register_architecture() {
    register_post_type( 'cbp_course', array(
        'labels'      => array(
            'name'          => 'Course Items',
            'singular_name' => 'Course Item',
            'add_new_item'  => 'Add New Course',
            'edit_item'     => 'Edit Course',
            'all_items'     => 'All Courses'
        ),
        'public'      => true,
        'show_ui'     => true,
        'menu_icon'   => 'dashicons-grid-view',
        'supports'    => array( 'title' ),
        'show_in_rest'=> true,
    ));

    register_taxonomy( 'cbp_project', 'cbp_course', array(
        'labels'       => array( 'name' => 'Projects', 'singular_name' => 'Project' ),
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
    ));

    register_taxonomy( 'cbp_tag', 'cbp_course', array(
        'labels'       => array( 'name' => 'Tags / Filters', 'singular_name' => 'Tag' ),
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
    ));
}

// 2. Add Meta Boxes
add_action( 'add_meta_boxes', 'cbp_add_meta_box' );
function cbp_add_meta_box() {
    add_meta_box( 'cbp_meta', 'Course Details', 'cbp_meta_callback', 'cbp_course', 'normal', 'high' );
}

function cbp_meta_callback( $post ) {
    $course_code  = get_post_meta( $post->ID, '_cbp_course_code', true );
    $duration     = get_post_meta( $post->ID, '_cbp_duration', true );
    $explore_link = get_post_meta( $post->ID, '_cbp_explore_link', true );
    $is_new       = get_post_meta( $post->ID, '_cbp_is_new', true );
    
    wp_nonce_field( 'cbp_save_meta', 'cbp_meta_nonce' );
    
    echo '<div style="display:flex; flex-direction:column; gap:15px; margin-top:10px;">';
    echo '<div><label style="font-weight:bold;">Course Code (e.g., AZ-900)</label><br/>';
    echo '<input type="text" name="cbp_course_code" value="' . esc_attr( $course_code ) . '" style="width:100%; max-width:400px;" /></div>';
    
    echo '<div><label style="font-weight:bold;">Duration (e.g., 4 Weeks, 1 Day)</label><br/>';
    echo '<input type="text" name="cbp_duration" value="' . esc_attr( $duration ) . '" style="width:100%; max-width:400px;" /></div>';
    
    echo '<div><label style="font-weight:bold;">Explore Button Link (URL)</label><br/>';
    echo '<input type="url" name="cbp_explore_link" value="' . esc_attr( $explore_link ) . '" style="width:100%; max-width:400px;" placeholder="https://" /></div>';
    
    echo '<div><label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
    echo '<input type="checkbox" name="cbp_is_new" value="yes" ' . checked( $is_new, 'yes', false ) . ' /> Show "New" Badge</label></div>';
    echo '</div>';
}

add_action( 'save_post', 'cbp_save_meta' );
function cbp_save_meta( $post_id ) {
    if ( ! isset( $_POST['cbp_meta_nonce'] ) || ! wp_verify_nonce( $_POST['cbp_meta_nonce'], 'cbp_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    
    update_post_meta( $post_id, '_cbp_is_new', isset( $_POST['cbp_is_new'] ) ? 'yes' : 'no' );
    
    if ( isset( $_POST['cbp_course_code'] ) ) update_post_meta( $post_id, '_cbp_course_code', sanitize_text_field( $_POST['cbp_course_code'] ) );
    if ( isset( $_POST['cbp_duration'] ) ) update_post_meta( $post_id, '_cbp_duration', sanitize_text_field( $_POST['cbp_duration'] ) );
    if ( isset( $_POST['cbp_explore_link'] ) ) update_post_meta( $post_id, '_cbp_explore_link', esc_url_raw( $_POST['cbp_explore_link'] ) );
}

// 3. The Front-End Shortcode
add_shortcode( 'course_board', 'cbp_board_shortcode' );
function cbp_board_shortcode( $atts ) {
    $args = shortcode_atts( array(
        'project'  => '', 
        'title'    => 'Course Board',
        'subtitle' => 'Filter and find the right courses.'
    ), $atts );

    if ( empty( $args['project'] ) ) return '<p style="color:red;">Please specify a project slug.</p>';

    $query_args = array(
        'post_type'      => 'cbp_course',
        'posts_per_page' => -1,
        'tax_query'      => array( array( 'taxonomy' => 'cbp_project', 'field' => 'slug', 'terms' => sanitize_text_field( $args['project'] ) ) ),
    );
    $query = new WP_Query( $query_args );

    $items = array();
    $filters_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            
            $item_terms = array();
            $item_tags_display = array();
            
            $course_code  = get_post_meta( $post_id, '_cbp_course_code', true );
            $duration     = get_post_meta( $post_id, '_cbp_duration', true );
            $explore_link = get_post_meta( $post_id, '_cbp_explore_link', true );
            $is_new_meta  = get_post_meta( $post_id, '_cbp_is_new', true );

            $terms = get_the_terms( $post_id, 'cbp_tag' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    if ( $term->parent != 0 ) {
                        $parent_term = get_term( $term->parent, 'cbp_tag' );
                        $parent_slug = $parent_term->slug;

                        if ( ! isset( $filters_data[$parent_slug] ) ) {
                            $filters_data[$parent_slug] = array( 'label' => $parent_term->name, 'options' => array() );
                        }
                        $filters_data[$parent_slug]['options'][$term->slug] = $term->name;

                        $item_terms[$parent_slug] = $term->slug;
                        $item_tags_display[$parent_slug] = $term->name;
                    }
                }
            }

            $items[] = array(
                'title'        => get_the_title(),
                'code'         => $course_code,
                'duration'     => $duration,
                'link'         => $explore_link,
                'terms'        => $item_terms, 
                'tags_display' => $item_tags_display,
                'isNew'        => ( $is_new_meta === 'yes' )
            );
        }
        wp_reset_postdata();
    }

    $grid_data = array( 'items' => $items, 'filters' => $filters_data );
    $uid = uniqid('cbp_'); 

    ob_start();
    ?>
    
    <style>
    .cbp-layout { font-family: 'Segoe UI', system-ui, sans-serif; max-width: 1180px; margin: 28px auto; display: flex; gap: 24px; align-items: flex-start; color: #1a1a2e; }
    .cbp-header { text-align: center; padding: 40px 24px 28px; background: #fff; border-bottom: 1px solid #e4e8ed; margin-bottom: 20px;}
    .cbp-header h2 { margin:0; font-size: clamp(22px, 3vw, 32px); font-weight: 700; color: #1a1a2e; }
    .cbp-header p { margin-top: 6px; font-size: 14px; color: #6b7280; }
    
    /* Sidebar */
    .cbp-sidebar { flex: 0 0 260px; background: #fff; border: 1px solid #e4e8ed; border-radius: 12px; padding: 24px 20px; position: sticky; top: 20px; }
    .cbp-sidebar-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #0078d4; }
    .cbp-filter-section { margin-bottom: 22px; }
    .cbp-filter-label { font-size: 11px; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; color: #9ca3af; margin-bottom: 10px; }
    .cbp-select { width: 100%; padding: 9px 12px; border: 1.5px solid #e4e8ed; border-radius: 8px; background: #f9fafb; font-size: 13.5px; cursor: pointer; }
    .cbp-reset { width: 100%; padding: 9px; border: 1.5px solid #e4e8ed; border-radius: 8px; background: transparent; color: #6b7280; cursor: pointer; margin-top: 4px; }
    
    /* Main Area */
    .cbp-main { flex: 1; min-width: 0; }
    .cbp-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
    .cbp-result-count { font-size: 18px; font-weight: 700; }
    .cbp-result-count span { color: #0078d4; }
    .cbp-search { border: 1.5px solid #e4e8ed; border-radius: 8px; padding: 8px 14px; width: 240px; font-size: 13.5px; }
    .cbp-list { display: flex; flex-direction: column; gap: 12px; }
    
    /* Refined Card Layout */
    .cbp-item { background: #fff; border: 1.5px solid #e4e8ed; border-radius: 10px; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 20px; transition: all .18s; border-left: 4px solid #0078d4; }
    .cbp-item:hover { border-color: #0078d4; box-shadow: 0 4px 16px rgba(0,120,212,.08); transform: translateX(3px); }
    
    /* Main Content Column */
    .cbp-item-main { flex: 1; display: flex; flex-direction: column; gap: 6px; }
    
    /* Top Row: Course Code */
    .cbp-item-top-row { display: flex; align-items: center; gap: 10px; margin-bottom: 2px; }
    .cbp-course-code { background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; }
    
    /* Title Row: Title + Badge */
    .cbp-item-title-wrapper { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
    .cbp-item-title { font-size: 17px; font-weight: 600; color: #1a1a2e; }
    .cbp-badge-new { background: #fff3cd; color: #856404; border: 1px solid #f5c842; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; }
    
    /* Meta Row (Tags + Duration inline) */
    .cbp-item-meta-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .cbp-item-tags { display: flex; flex-wrap: wrap; gap: 8px; }
    .cbp-tag { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
    
    .cbp-duration { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #6b7280; }
    
    /* Action Button Column */
    .cbp-item-action { flex-shrink: 0; display: flex; align-items: center; }
    .cbp-btn-explore { background: #0078d4; color: #ffffff; text-decoration: none; padding: 9px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; transition: background 0.2s; white-space: nowrap; }
    .cbp-btn-explore:hover { background: #005a9e; color: #ffffff; }

    .cbp-empty { text-align: center; padding: 60px 20px; color: #9ca3af; display: none; }
    
    /* Mobile View */
    @media (max-width: 700px) { 
        .cbp-layout { flex-direction: column; } 
        .cbp-sidebar { position: static; width: 100%; } 
        .cbp-item { flex-direction: column; align-items: flex-start; }
        .cbp-item-action { width: 100%; justify-content: flex-start; margin-top: 10px; }
    }
    </style>

    <div class="cbp-header">
        <h2><?php echo esc_html($args['title']); ?></h2>
        <p><?php echo esc_html($args['subtitle']); ?></p>
    </div>

    <div class="cbp-layout" id="<?php echo $uid; ?>">
      <aside class="cbp-sidebar">
        <div class="cbp-sidebar-title">Filters</div>
        <div id="dynamic-filters-<?php echo $uid; ?>"></div>
        <button class="cbp-reset" id="resetBtn-<?php echo $uid; ?>">↺ Reset Filters</button>
      </aside>

      <main class="cbp-main">
        <div class="cbp-topbar">
          <div class="cbp-result-count" id="resultCount-<?php echo $uid; ?>"><span>0</span> Items</div>
          <input class="cbp-search" id="searchInput-<?php echo $uid; ?>" type="search" placeholder="Search...">
        </div>
        <div class="cbp-list" id="courseList-<?php echo $uid; ?>"></div>
        <div class="cbp-empty" id="emptyState-<?php echo $uid; ?>"><h3>No results found</h3></div>
      </main>
    </div>

    <script>
    (function() {
        const uid = "<?php echo $uid; ?>";
        const DATA = <?php echo wp_json_encode( $grid_data ); ?>;
        const ITEMS = DATA.items;
        const FILTERS = DATA.filters;

        const container   = document.getElementById(uid);
        const filterArea  = document.getElementById('dynamic-filters-' + uid);
        const list        = document.getElementById('courseList-' + uid);
        const emptyState  = document.getElementById('emptyState-' + uid);
        const resultCount = document.getElementById('resultCount-' + uid);
        const searchInput = document.getElementById('searchInput-' + uid);
        const resetBtn    = document.getElementById('resetBtn-' + uid);

        const activeFilters = {}; 

        // Generates color based on the parent string, ensuring consistent family colors
        function stringToColor(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) { hash = str.charCodeAt(i) + ((hash << 5) - hash); }
            const h = Math.abs(hash) % 360; 
            return `background-color: hsl(${h}, 70%, 94%); color: hsl(${h}, 70%, 30%); border: 1px solid hsl(${h}, 70%, 85%);`;
        }

        for (const [taxSlug, taxData] of Object.entries(FILTERS)) {
            activeFilters[taxSlug] = 'all'; 
            const section = document.createElement('div');
            section.className = 'cbp-filter-section';
            
            let optionsHtml = `<option value="all">All ${taxData.label}s</option>`;
            const sortedOptions = Object.entries(taxData.options).sort((a, b) => a[1].localeCompare(b[1]));
            for (const [termSlug, termName] of sortedOptions) {
                optionsHtml += `<option value="${termSlug}">${termName}</option>`;
            }

            section.innerHTML = `
                <div class="cbp-filter-label">${taxData.label}</div>
                <select class="cbp-select dynamic-select" data-tax="${taxSlug}">
                    ${optionsHtml}
                </select>
            `;
            filterArea.appendChild(section);
        }

        function render() {
            const q = searchInput.value.toLowerCase().trim();

            const filtered = ITEMS.filter(item => {
                let matchesFilters = true;
                for (const taxSlug in activeFilters) {
                    const selectedValue = activeFilters[taxSlug];
                    if (selectedValue !== 'all' && item.terms[taxSlug] !== selectedValue) {
                        matchesFilters = false;
                        break;
                    }
                }
                const searchString = (item.title + ' ' + (item.code || '')).toLowerCase();
                const matchesSearch = !q || searchString.includes(q);
                
                return matchesFilters && matchesSearch;
            });

            list.innerHTML = '';
            if (!filtered.length) {
                emptyState.style.display = 'block';
                resultCount.innerHTML = '<span>0</span> Items';
                return;
            }
            emptyState.style.display = 'none';
            resultCount.innerHTML = `<span>${filtered.length}</span> Items`;

            filtered.forEach((item) => {
                const el = document.createElement('div');
                el.className = 'cbp-item';
                
                let tagsHtml = '';
                for (const taxSlug in FILTERS) {
                    if (item.tags_display && item.tags_display[taxSlug]) {
                        const tagName = item.tags_display[taxSlug];
                        // NEW LOGIC: Pass the parent's label (e.g. "Skill Level") to dictate the color family
                        const parentLabel = FILTERS[taxSlug].label; 
                        tagsHtml += `<span class="cbp-tag" style="${stringToColor(parentLabel)}">${tagName}</span>`;
                    }
                }

                // Course Code (Top Row)
                let topRowHtml = '';
                if (item.code) {
                    topRowHtml = `
                    <div class="cbp-item-top-row">
                        <span class="cbp-course-code">${item.code}</span>
                    </div>`;
                }

                // Title + Badge (Title Row)
                const badgeHtml = item.isNew ? `<span class="cbp-badge-new">New</span>` : '';
                
                // Duration & Button
                const durationIcon = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>`;
                const durationHtml = item.duration ? `<div class="cbp-duration">${durationIcon} ${item.duration}</div>` : '';
                const btnHtml = item.link ? `<a href="${item.link}" target="_blank" class="cbp-btn-explore">Explore</a>` : '';

                el.innerHTML = `
                  <div class="cbp-item-main">
                    
                    ${topRowHtml}
                    
                    <div class="cbp-item-title-wrapper">
                        <div class="cbp-item-title">${item.title}</div>
                        ${badgeHtml}
                    </div>
                    
                    <div class="cbp-item-meta-row">
                        <div class="cbp-item-tags">${tagsHtml}</div>
                        ${durationHtml}
                    </div>

                  </div>
                  
                  <div class="cbp-item-action">
                    ${btnHtml}
                  </div>
                `;
                list.appendChild(el);
            });
        }

        const selects = container.querySelectorAll('.dynamic-select');
        selects.forEach(select => {
            select.addEventListener('change', (e) => {
                activeFilters[e.target.dataset.tax] = e.target.value;
                render();
            });
        });

        searchInput.addEventListener('input', render);
        
        resetBtn.addEventListener('click', () => {
            selects.forEach(s => s.value = 'all');
            for (let key in activeFilters) activeFilters[key] = 'all';
            searchInput.value = '';
            render();
        });

        render(); 
    })();
    </script>
    <?php
    return ob_get_clean();
}