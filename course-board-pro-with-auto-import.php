<?php
/**
 * Plugin Name: Course Board Pro
 * Description: Create isolated course boards with dynamic filters, custom fields, and pagination.
 * Version: 3.6
 * Author: Tharindu Kalhara
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
        'subtitle' => 'Filter and find the right courses.',
        'per_page' => '5' // Added default setting for pagination
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

                        // Build Dropdown Options
                        if ( ! isset( $filters_data[$parent_slug] ) ) {
                            $filters_data[$parent_slug] = array( 'label' => $parent_term->name, 'options' => array() );
                        }
                        $filters_data[$parent_slug]['options'][$term->slug] = $term->name;

                        // FIX 1: Capture multiple tags as an array instead of overwriting strings
                        if ( ! isset( $item_terms[$parent_slug] ) ) {
                            $item_terms[$parent_slug] = array();
                            $item_tags_display[$parent_slug] = array();
                        }
                        $item_terms[$parent_slug][] = $term->slug;
                        $item_tags_display[$parent_slug][] = $term->name;
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

    $grid_data = array( 
        'items' => $items, 
        'filters' => $filters_data,
        'settings' => array( 'per_page' => intval( $args['per_page'] ) )
    );
    
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
    
    /* Top Row */
    .cbp-item-top-row { display: flex; align-items: center; gap: 10px; margin-bottom: 2px; }
    .cbp-course-code { background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; }
    
    /* Title Row */
    .cbp-item-title-wrapper { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
    .cbp-item-title { font-size: 17px; font-weight: 600; color: #1a1a2e; }
    .cbp-badge-new { background: #fff3cd; color: #856404; border: 1px solid #f5c842; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; }
    
    /* Meta Row */
    .cbp-item-meta-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .cbp-item-tags { display: flex; flex-wrap: wrap; gap: 8px; }
    .cbp-tag { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
    .cbp-duration { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #6b7280; }
    
    /* Action Button */
    .cbp-item-action { flex-shrink: 0; display: flex; align-items: center; }
    .cbp-btn-explore { background: #0078d4; color: #ffffff; text-decoration: none; padding: 9px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; transition: background 0.2s; white-space: nowrap; }
    .cbp-btn-explore:hover { background: #005a9e; color: #ffffff; }

    /* Pagination Styles */
    .cbp-pagination { display: flex; justify-content: center; align-items: center; gap: 5px; margin-top: 28px; flex-wrap: wrap; }
    .cbp-page-btn { background: #fff; border: 1.5px solid #e4e8ed; padding: 7px 14px; border-radius: 8px; cursor: pointer; color: #374151; font-weight: 600; font-size: 13px; transition: all 0.18s; min-width: 38px; text-align: center; line-height: 1; }
    .cbp-page-btn:hover:not(:disabled) { border-color: #0078d4; color: #0078d4; background: #f0f7ff; }
    .cbp-page-btn.active { background: #0078d4; color: #fff; border-color: #0078d4; box-shadow: 0 2px 8px rgba(0,120,212,0.25); }
    .cbp-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .cbp-page-btn.nav { padding: 7px 16px; color: #0078d4; border-color: #0078d4; }
    .cbp-page-btn.nav:disabled { color: #9ca3af; border-color: #e4e8ed; }
    .cbp-page-ellipsis { padding: 7px 4px; color: #9ca3af; font-weight: 700; font-size: 15px; letter-spacing: 2px; user-select: none; }

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
        <div id="pagination-<?php echo $uid; ?>"></div>
        <div class="cbp-empty" id="emptyState-<?php echo $uid; ?>"><h3>No results found</h3></div>
      </main>
    </div>

    <script>
    (function() {
        const uid = "<?php echo $uid; ?>";
        const DATA = <?php echo wp_json_encode( $grid_data ); ?>;
        const ITEMS = DATA.items;
        const FILTERS = DATA.filters;
        const itemsPerPage = DATA.settings.per_page; // Controlled via shortcode

        const container   = document.getElementById(uid);
        const filterArea  = document.getElementById('dynamic-filters-' + uid);
        const list        = document.getElementById('courseList-' + uid);
        const pagContainer= document.getElementById('pagination-' + uid);
        const emptyState  = document.getElementById('emptyState-' + uid);
        const resultCount = document.getElementById('resultCount-' + uid);
        const searchInput = document.getElementById('searchInput-' + uid);
        const resetBtn    = document.getElementById('resetBtn-' + uid);

        const activeFilters = {}; 
        let currentPage = 1;

        function stringToColor(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) { hash = str.charCodeAt(i) + ((hash << 5) - hash); }
            const h = Math.abs(hash) % 360; 
            return `background-color: hsl(${h}, 70%, 94%); color: hsl(${h}, 70%, 30%); border: 1px solid hsl(${h}, 70%, 85%);`;
        }

        // Build Filters
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

        function render(resetPage = false) {
            if (resetPage) currentPage = 1;
            const q = searchInput.value.toLowerCase().trim();

            const filtered = ITEMS.filter(item => {
                let matchesFilters = true;
                for (const taxSlug in activeFilters) {
                    const selectedValue = activeFilters[taxSlug];
                    if (selectedValue !== 'all') {
                        // FIX: Ensure it checks the array of terms, resolving the multi-tag bug
                        if (!item.terms[taxSlug] || !item.terms[taxSlug].includes(selectedValue)) {
                            matchesFilters = false;
                            break;
                        }
                    }
                }
                const searchString = (item.title + ' ' + (item.code || '')).toLowerCase();
                const matchesSearch = !q || searchString.includes(q);
                
                return matchesFilters && matchesSearch;
            });

            // Pagination Math
            const totalPages = Math.ceil(filtered.length / itemsPerPage);
            const paginatedItems = filtered.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

            list.innerHTML = '';
            if (!filtered.length) {
                emptyState.style.display = 'block';
                pagContainer.innerHTML = '';
                resultCount.innerHTML = '<span>0</span> Items';
                return;
            }
            
            emptyState.style.display = 'none';
            resultCount.innerHTML = `<span>${filtered.length}</span> Items`;

            // Render Cards for this page only
            paginatedItems.forEach((item) => {
                const el = document.createElement('div');
                el.className = 'cbp-item';
                
                let tagsHtml = '';
                for (const taxSlug in FILTERS) {
                    if (item.tags_display && item.tags_display[taxSlug]) {
                        const parentLabel = FILTERS[taxSlug].label; 
                        // FIX: Loop through array and print ALL tags assigned to this course under this parent
                        item.tags_display[taxSlug].forEach(tagName => {
                            tagsHtml += `<span class="cbp-tag" style="${stringToColor(parentLabel)}">${tagName}</span>`;
                        });
                    }
                }

                let topRowHtml = '';
                if (item.code) {
                    topRowHtml = `<div class="cbp-item-top-row"><span class="cbp-course-code">${item.code}</span></div>`;
                }

                const badgeHtml = item.isNew ? `<span class="cbp-badge-new">New</span>` : '';
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
                  <div class="cbp-item-action">${btnHtml}</div>
                `;
                list.appendChild(el);
            });

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            pagContainer.innerHTML = '';
            if (totalPages <= 1) return;

            const wrap = document.createElement('div');
            wrap.className = 'cbp-pagination';

            const goTo = (page) => { currentPage = page; render(); };

            const makeBtn = (label, page, extraClass = '') => {
                const btn = document.createElement('button');
                btn.className = 'cbp-page-btn ' + extraClass;
                btn.innerHTML = label;
                if (page !== null) {
                    if (page === currentPage) btn.classList.add('active');
                    btn.addEventListener('click', () => goTo(page));
                }
                return btn;
            };

            const makeEllipsis = () => {
                const span = document.createElement('span');
                span.className = 'cbp-page-ellipsis';
                span.textContent = '…';
                return span;
            };

            // ← First & Prev
            const firstBtn = makeBtn('«', 1, 'nav');
            firstBtn.title = 'First page';
            firstBtn.disabled = currentPage === 1;
            wrap.appendChild(firstBtn);

            const prevBtn = makeBtn('‹ Prev', currentPage > 1 ? currentPage - 1 : null, 'nav');
            prevBtn.disabled = currentPage === 1;
            wrap.appendChild(prevBtn);

            // Build visible page numbers using ellipsis logic
            // Always show: 1, 2, [current-1, current, current+1], last-1, last
            const alwaysShow = new Set([1, 2, currentPage - 1, currentPage, currentPage + 1, totalPages - 1, totalPages]);
            const pages = [];
            for (let i = 1; i <= totalPages; i++) { if (alwaysShow.has(i) && i >= 1 && i <= totalPages) pages.push(i); }

            let prev = null;
            pages.forEach(p => {
                if (prev !== null && p - prev > 1) wrap.appendChild(makeEllipsis());
                wrap.appendChild(makeBtn(p, p));
                prev = p;
            });

            // Next & Last →
            const nextBtn = makeBtn('Next ›', currentPage < totalPages ? currentPage + 1 : null, 'nav');
            nextBtn.disabled = currentPage === totalPages;
            wrap.appendChild(nextBtn);

            const lastBtn = makeBtn('»', totalPages, 'nav');
            lastBtn.title = 'Last page';
            lastBtn.disabled = currentPage === totalPages;
            wrap.appendChild(lastBtn);

            pagContainer.appendChild(wrap);
        }

        const selects = container.querySelectorAll('.dynamic-select');
        selects.forEach(select => {
            select.addEventListener('change', (e) => {
                activeFilters[e.target.dataset.tax] = e.target.value;
                render(true); // reset to page 1 on filter
            });
        });

        searchInput.addEventListener('input', () => render(true)); // reset to page 1 on search
        
        resetBtn.addEventListener('click', () => {
            selects.forEach(s => s.value = 'all');
            for (let key in activeFilters) activeFilters[key] = 'all';
            searchInput.value = '';
            render(true);
        });

        render(); 
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// IMPORT FEATURE (added in v3.6)
// ============================================================================

// 5. Register Admin Import Page
add_action( 'admin_menu', 'cbp_add_import_menu' );
function cbp_add_import_menu() {
    add_submenu_page(
        'edit.php?post_type=cbp_course',
        'Import Courses',
        '📥 Import Courses',
        'manage_options',
        'cbp-import',
        'cbp_import_page'
    );
}

// 6. Handle the CSV upload & import (runs before any output)
add_action( 'admin_init', 'cbp_handle_import' );
function cbp_handle_import() {
    if ( ! isset( $_POST['cbp_do_import'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
    check_admin_referer( 'cbp_import_action', 'cbp_import_nonce' );

    if ( empty( $_FILES['cbp_csv']['tmp_name'] ) ) {
        set_transient( 'cbp_import_result', [ 'error' => 'No file uploaded.' ], 60 );
        return;
    }

    $file = $_FILES['cbp_csv']['tmp_name'];
    $handle = fopen( $file, 'r' );
    if ( ! $handle ) {
        set_transient( 'cbp_import_result', [ 'error' => 'Could not read uploaded file.' ], 60 );
        return;
    }

    // Strip UTF-8 BOM if present
    $bom = fread( $handle, 3 );
    if ( $bom !== "\xEF\xBB\xBF" ) rewind( $handle );

    $header = fgetcsv( $handle );
    if ( ! $header ) {
        set_transient( 'cbp_import_result', [ 'error' => 'CSV file is empty or unreadable.' ], 60 );
        return;
    }

    // Trim header cells
    $header = array_map( 'trim', $header );

    // Required column names → internal keys
    $column_map = [
        'Projects'            => 'project',
        'Credential Type'     => 'credential_type',
        'Solution Area'       => 'solution_area',
        'Title'               => 'title',
        'Course Code'         => 'course_code',
        'Duration'            => 'duration',
        'Explore Button Link' => 'explore_link',
    ];

    $col_index = [];
    foreach ( $header as $i => $col ) {
        if ( isset( $column_map[ $col ] ) ) {
            $col_index[ $column_map[$col] ] = $i;
        }
    }

    foreach ( [ 'title', 'project' ] as $req ) {
        if ( ! isset( $col_index[$req] ) ) {
            set_transient( 'cbp_import_result', [ 'error' => 'Missing required column: "' . $req . '". Check your CSV headers.' ], 60 );
            fclose( $handle );
            return;
        }
    }

    // Term cache to avoid repeated DB hits
    $term_cache = [];

    $get_or_create_term = function( $name, $taxonomy, $parent_name = null ) use ( &$term_cache ) {
        $name        = trim( $name );
        $cache_key   = $taxonomy . '|' . ( $parent_name ? $parent_name . '>' : '' ) . $name;
        if ( isset( $term_cache[$cache_key] ) ) return $term_cache[$cache_key];

        $parent_id = 0;
        if ( $parent_name ) {
            $pk = $taxonomy . '|' . $parent_name;
            if ( isset( $term_cache[$pk] ) ) {
                $parent_id = $term_cache[$pk];
            } else {
                $pt = get_term_by( 'name', $parent_name, $taxonomy );
                if ( ! $pt ) {
                    $r = wp_insert_term( $parent_name, $taxonomy, [ 'slug' => sanitize_title( $parent_name ) ] );
                    $parent_id = is_wp_error( $r ) ? 0 : $r['term_id'];
                } else {
                    $parent_id = $pt->term_id;
                }
                $term_cache[$pk] = $parent_id;
            }
        }

        $existing = get_term_by( 'name', $name, $taxonomy );
        if ( $existing && ( ! $parent_name || (int) $existing->parent === $parent_id ) ) {
            $tid = $existing->term_id;
        } else {
            $args = [ 'slug' => sanitize_title( $name ) ];
            if ( $parent_id ) $args['parent'] = $parent_id;
            $r   = wp_insert_term( $name, $taxonomy, $args );
            $tid = is_wp_error( $r )
                ? ( isset( $r->error_data['term_exists'] ) ? $r->error_data['term_exists'] : 0 )
                : $r['term_id'];
        }

        $term_cache[$cache_key] = $tid;
        return $tid;
    };

    $stats = [ 'imported' => 0, 'skipped' => 0, 'errors' => [] ];

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $cell = function( $key ) use ( $row, $col_index ) {
            if ( ! isset( $col_index[$key] ) ) return '';
            return trim( $row[ $col_index[$key] ] ?? '' );
        };

        $title        = $cell('title');
        $course_code  = $cell('course_code');
        $duration     = $cell('duration');
        $explore_link = $cell('explore_link');
        $project_val  = $cell('project');
        $cred_type    = $cell('credential_type');
        $sol_area     = $cell('solution_area');

        if ( ! $title && ! $course_code ) continue; // blank row

        if ( ! $title ) {
            $stats['skipped']++;
            continue;
        }

        // Duplicate check by course code
        if ( $course_code ) {
            $dupe = new WP_Query([
                'post_type'      => 'cbp_course',
                'meta_key'       => '_cbp_course_code',
                'meta_value'     => $course_code,
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ]);
            if ( $dupe->have_posts() ) {
                $stats['skipped']++;
                continue;
            }
        }

        $post_id = wp_insert_post([
            'post_title'  => wp_strip_all_tags( $title ),
            'post_type'   => 'cbp_course',
            'post_status' => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            $stats['errors'][] = $title . ': ' . $post_id->get_error_message();
            continue;
        }

        if ( $course_code )  update_post_meta( $post_id, '_cbp_course_code',  sanitize_text_field( $course_code ) );
        if ( $duration )     update_post_meta( $post_id, '_cbp_duration',     sanitize_text_field( $duration ) );
        if ( $explore_link ) update_post_meta( $post_id, '_cbp_explore_link', esc_url_raw( $explore_link ) );
        update_post_meta( $post_id, '_cbp_is_new', 'no' );

        $tag_ids = [];

        if ( $project_val ) {
            $pid = $get_or_create_term( $project_val, 'cbp_project', null );
            if ( $pid ) wp_set_object_terms( $post_id, [ (int) $pid ], 'cbp_project' );
        }
        if ( $cred_type ) {
            $tid = $get_or_create_term( $cred_type, 'cbp_tag', 'Credential Type' );
            if ( $tid ) $tag_ids[] = (int) $tid;
        }
        if ( $sol_area ) {
            $tid = $get_or_create_term( $sol_area, 'cbp_tag', 'Solution Area' );
            if ( $tid ) $tag_ids[] = (int) $tid;
        }
        if ( $tag_ids ) wp_set_object_terms( $post_id, $tag_ids, 'cbp_tag' );

        $stats['imported']++;
    }

    fclose( $handle );
    set_transient( 'cbp_import_result', $stats, 60 );
}

// 7. Render the Import Admin Page
function cbp_import_page() {
    $result = get_transient( 'cbp_import_result' );
    delete_transient( 'cbp_import_result' );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">📥 Import Courses</h1>
        <p style="color:#6b7280;margin-top:4px;">Upload a CSV file to bulk-import courses into Course Board Pro.</p>

        <?php if ( $result ): ?>
            <?php if ( isset( $result['error'] ) ): ?>
                <div class="notice notice-error"><p><strong>Import failed:</strong> <?php echo esc_html( $result['error'] ); ?></p></div>
            <?php else: ?>
                <div class="notice notice-success" style="padding:16px 20px;border-radius:6px;">
                    <strong>✅ Import complete!</strong><br><br>
                    <span style="font-size:15px;">
                        🟢 <strong><?php echo intval( $result['imported'] ); ?></strong> courses imported &nbsp;|&nbsp;
                        🟡 <strong><?php echo intval( $result['skipped'] ); ?></strong> skipped (duplicates / blank rows)
                        <?php if ( ! empty( $result['errors'] ) ): ?>
                            &nbsp;|&nbsp; 🔴 <strong><?php echo count( $result['errors'] ); ?></strong> errors
                        <?php endif; ?>
                    </span>
                    <?php if ( ! empty( $result['errors'] ) ): ?>
                        <details style="margin-top:10px;"><summary>Show errors</summary>
                        <ul><?php foreach ( $result['errors'] as $e ): ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:30px;max-width:640px;margin-top:20px;">

            <h3 style="margin-top:0;">📄 Required CSV Format</h3>
            <p style="color:#6b7280;font-size:13px;">Your CSV must have these exact column headers in the first row:</p>
            <code style="display:block;background:#f3f4f6;padding:10px 14px;border-radius:6px;font-size:12.5px;line-height:1.8;">
                Projects, Credential Type, Solution Area, Title, Course Code, Duration, Explore Button Link
            </code>
            <p style="font-size:12px;color:#9ca3af;margin-top:8px;">
                💡 To export from Excel: <strong>File → Save As → CSV UTF-8 (Comma delimited)</strong>
            </p>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

            <h3 style="margin-top:0;">⬆️ Upload & Import</h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'cbp_import_action', 'cbp_import_nonce' ); ?>
                <input type="hidden" name="cbp_do_import" value="1">

                <div style="border:2px dashed #d1d5db;border-radius:8px;padding:28px;text-align:center;background:#fafafa;margin-bottom:20px;">
                    <div style="font-size:32px;margin-bottom:8px;">📂</div>
                    <input type="file" name="cbp_csv" accept=".csv" required
                           style="display:block;margin:0 auto;font-size:14px;">
                    <p style="font-size:12px;color:#9ca3af;margin:10px 0 0;">Accepted format: .csv</p>
                </div>

                <button type="submit" class="button button-primary" style="height:40px;padding:0 24px;font-size:14px;">
                    Import Courses
                </button>
            </form>
        </div>

        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:20px 24px;max-width:640px;margin-top:20px;">
            <strong>ℹ️ How it works</strong>
            <ul style="margin:10px 0 0;padding-left:20px;font-size:13px;color:#6b7280;line-height:2;">
                <li>Courses are matched by <strong>Course Code</strong> — duplicates are automatically skipped.</li>
                <li>Projects, Credential Types, and Solution Areas are <strong>created automatically</strong> if they don't exist yet.</li>
                <li>All new courses are published immediately with the <strong>"New" badge off</strong> by default.</li>
                <li>You can safely re-upload the same file — already-imported courses will be skipped.</li>
            </ul>
        </div>
    </div>
    <?php
}