<?php
/**
 * Admin pages
 */

if (!defined('ABSPATH')) exit;

class CP_Admin {
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Customer Portal',
            'Customer Portal',
            'manage_options',
            'customer-portal',
            array($this, 'users_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'customer-portal',
            'Users',
            'Users',
            'manage_options',
            'customer-portal',
            array($this, 'users_page')
        );
        
        add_submenu_page(
            'customer-portal',
            'Checklists',
            'Checklists',
            'manage_options',
            'customer-portal-checklists',
            array($this, 'checklists_page')
        );
        
        add_submenu_page(
            'customer-portal',
            'Links',
            'Links',
            'manage_options',
            'customer-portal-links',
            array($this, 'links_page')
        );

        add_submenu_page(
            'customer-portal',
            'Calendar & Bookings',
            'Calendar',
            'manage_options',
            'customer-portal-calendar',
            array($this, 'calendar_page')
        );

        add_submenu_page(
            'customer-portal',
            'Surveys',
            'Surveys',
            'manage_options',
            'customer-portal-surveys',
            array($this, 'surveys_page')
        );

        add_submenu_page(
            'customer-portal',
            'Settings',
            'Settings',
            'manage_options',
            'customer-portal-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Users page
     */
    public function users_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }
        
        // Handle actions
        if (isset($_POST['action']) && isset($_POST['cp_admin_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_admin_nonce'], 'cp_admin_action')) {
                wp_die(__('Security check failed.'));
            }
            
            if ($_POST['action'] === 'toggle_user' && isset($_POST['user_id'])) {
                CP()->database->toggle_user_status(intval($_POST['user_id']));
            }
            
            if ($_POST['action'] === 'update_folder' && isset($_POST['user_id']) && isset($_POST['folder_id'])) {
                CP()->database->update_user_folder(
                    intval($_POST['user_id']),
                    sanitize_text_field($_POST['folder_id'])
                );
                echo '<div class="notice notice-success"><p>Folder ID updated!</p></div>';
            }
        }
        
        $users = CP()->database->get_all_users();
        
        ?>
        <div class="wrap">
            <h1>Customer Portal - Users</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Telegram ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Drive Folder</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8">No users yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->telegram_id); ?></td>
                            <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
                            <td><?php echo esc_html($user->username ? '@' . $user->username : '-'); ?></td>
                            <td><?php echo !empty($user->email) ? esc_html($user->email) : '-'; ?></td>
                            <td>
                                <form method="post" style="display:inline-block; max-width: 350px;">
                                    <?php wp_nonce_field('cp_admin_action', 'cp_admin_nonce'); ?>
                                    <input type="hidden" name="action" value="update_folder">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->id); ?>">
                                    <div style="display:flex; gap:5px;">
                                        <input type="text" name="folder_id" value="<?php echo esc_attr($user->drive_folder_id); ?>" 
                                               placeholder="Google Drive Folder ID" style="flex:1; min-width:180px;">
                                        <button type="submit" class="button button-small">Save</button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <span style="color: <?php echo $user->is_active ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                    <?php echo $user->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($user->created_at); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('cp_admin_action', 'cp_admin_nonce'); ?>
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->id); ?>">
                                    <button type="submit" class="button">
                                        <?php echo $user->is_active ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Checklists page
     */
    public function checklists_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }
        
        global $wpdb;
        $checklists_table = $wpdb->prefix . 'customer_portal_checklists';
        $items_table = $wpdb->prefix . 'customer_portal_checklist_items';
        $users_table = $wpdb->prefix . 'customer_portal_users';
        
        // Handle actions
        if (isset($_POST['cp_action']) && isset($_POST['cp_checklist_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_checklist_nonce'], 'cp_checklist_action')) {
                wp_die(__('Security check failed.'));
            }
            
            if ($_POST['cp_action'] === 'create_checklist' && isset($_POST['user_id'])) {
                $wpdb->insert($checklists_table, array(
                    'user_id' => intval($_POST['user_id']),
                    'title' => sanitize_text_field($_POST['title']),
                    'type' => sanitize_text_field($_POST['type']),
                    'status' => 'active'
                ));
                echo '<div class="notice notice-success"><p>Checklist created!</p></div>';
            }
            
            if ($_POST['cp_action'] === 'add_item' && isset($_POST['checklist_id'])) {
                $checklist_id = intval($_POST['checklist_id']);
                $max_order = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(sort_order) FROM {$items_table} WHERE checklist_id = %d",
                    $checklist_id
                ));
                
                $wpdb->insert($items_table, array(
                    'checklist_id' => $checklist_id,
                    'product_name' => sanitize_text_field($_POST['product_name']),
                    'sort_order' => ($max_order + 1)
                ));
                echo '<div class="notice notice-success"><p>Item added!</p></div>';
            }
            
            if ($_POST['cp_action'] === 'bulk_add_items' && isset($_POST['checklist_id'])) {
                $checklist_id = intval($_POST['checklist_id']);
                $bulk_text = $_POST['bulk_text'];
                
                $checklist = $wpdb->get_row($wpdb->prepare(
                    "SELECT type FROM {$checklists_table} WHERE id = %d",
                    $checklist_id
                ));
                
                $max_order = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(sort_order) FROM {$items_table} WHERE checklist_id = %d",
                    $checklist_id
                )) ?: 0;
                
                $inserted = 0;
                
                if ($checklist && $checklist->type === 'bagatinatajs') {
                    $lines = preg_split('/\r\n|\r|\n/', $bulk_text);
                    $current_store = '';
                    $current_discount = '';
                    
                    foreach ($lines as $line) {
                        $line = preg_replace('/^\s+|\s+$/u', '', $line);
                        if (empty($line)) continue;
                        
                        if (preg_match('/^\[(.+?)\]$/u', $line, $matches)) {
                            $current_store = trim($matches[1]);
                            continue;
                        }
                        
                        $parts = array_map('trim', explode('|', $line));
                        $product_name = isset($parts[0]) ? trim($parts[0]) : '';
                        $link = isset($parts[1]) ? trim($parts[1]) : '';
                        $discount = isset($parts[2]) ? trim($parts[2]) : '';
                        
                        if (!empty($discount)) {
                            $current_discount = $discount;
                        }
                        
                        if (!empty($product_name)) {
                            $max_order++;
                            $result = $wpdb->insert($items_table, array(
                                'checklist_id' => $checklist_id,
                                'product_name' => sanitize_text_field($product_name),
                                'link' => esc_url_raw($link),
                                'store_name' => sanitize_text_field($current_store),
                                'discount_code' => sanitize_text_field($discount ?: $current_discount),
                                'sort_order' => $max_order
                            ));
                            if ($result) $inserted++;
                        }
                    }
                } else {
                    $lines = preg_split('/\r\n|\r|\n/', $bulk_text);
                    $products = array();
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        $items = explode(',', $line);
                        foreach ($items as $item) {
                            $item = trim($item);
                            if (!empty($item)) {
                                $products[] = $item;
                            }
                        }
                    }
                    
                    $products = array_unique($products);
                    
                    foreach ($products as $product) {
                        $max_order++;
                        $result = $wpdb->insert($items_table, array(
                            'checklist_id' => $checklist_id,
                            'product_name' => sanitize_text_field($product),
                            'sort_order' => $max_order
                        ));
                        if ($result) $inserted++;
                    }
                }
                
                echo '<div class="notice notice-success"><p>' . esc_html($inserted) . ' items added!</p></div>';
            }
            
            if ($_POST['cp_action'] === 'delete_item' && isset($_POST['item_id'])) {
                $wpdb->delete($items_table, array('id' => intval($_POST['item_id'])));
            }
            
            if ($_POST['cp_action'] === 'archive_checklist' && isset($_POST['checklist_id'])) {
                $wpdb->update($checklists_table, array('status' => 'archived'), array('id' => intval($_POST['checklist_id'])));
            }
        }
        
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        $checklist_id = isset($_GET['checklist_id']) ? intval($_GET['checklist_id']) : 0;
        
        if ($view === 'edit' && $checklist_id) {
            $this->edit_checklist_page($checklist_id);
        } else {
            $this->list_checklists_page();
        }
    }
    
    /**
     * List checklists
     */
    private function list_checklists_page() {
        global $wpdb;
        $checklists_table = $wpdb->prefix . 'customer_portal_checklists';
        $items_table = $wpdb->prefix . 'customer_portal_checklist_items';
        $users_table = $wpdb->prefix . 'customer_portal_users';
        
        $checklists = $wpdb->get_results("
            SELECT c.*, u.first_name, u.last_name,
                   COUNT(i.id) as total_items,
                   SUM(i.is_checked) as checked_items
            FROM {$checklists_table} c
            LEFT JOIN {$users_table} u ON c.user_id = u.id
            LEFT JOIN {$items_table} i ON c.id = i.checklist_id
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        
        $users = CP()->database->get_active_users();
        
        ?>
        <div class="wrap">
            <h1>Checklists</h1>
            
            <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h2>Create New Checklist</h2>
                <form method="post">
                    <?php wp_nonce_field('cp_checklist_action', 'cp_checklist_nonce'); ?>
                    <input type="hidden" name="cp_action" value="create_checklist">
                    <table class="form-table">
                        <tr>
                            <th><label>Client</label></th>
                            <td>
                                <select name="user_id" required>
                                    <option value="">Select client...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo esc_attr($user->id); ?>">
                                            <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Title</label></th>
                            <td><input type="text" name="title" value="Saraksts - <?php echo esc_attr(date('d.m.Y')); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label>Type</label></th>
                            <td>
                                <select name="type">
                                    <option value="veikals">Veikals (Simple)</option>
                                    <option value="bagatinatajs">Bagātinātāji (With links)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><input type="submit" class="button button-primary" value="Create"></p>
                </form>
            </div>
            
            <h2>Active Checklists</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Progress</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($checklists)): ?>
                        <tr><td colspan="6">No checklists yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($checklists as $c): ?>
                        <?php
                            $total = $c->total_items ?: 0;
                            $checked = $c->checked_items ?: 0;
                            $pct = $total > 0 ? round(($checked / $total) * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html($c->first_name . ' ' . $c->last_name); ?></td>
                            <td><strong><?php echo esc_html($c->title); ?></strong></td>
                            <td><?php echo esc_html(ucfirst($c->type)); ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="flex:1; background:#f0f0f0; border-radius:10px; height:20px; overflow:hidden;">
                                        <div style="background:#0073aa; height:100%; width:<?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span><?php echo $checked; ?>/<?php echo $total; ?></span>
                                </div>
                            </td>
                            <td><?php echo esc_html($c->created_at); ?></td>
                            <td>
                                <a href="?page=customer-portal-checklists&view=edit&checklist_id=<?php echo $c->id; ?>" class="button">Edit</a>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('cp_checklist_action', 'cp_checklist_nonce'); ?>
                                    <input type="hidden" name="cp_action" value="archive_checklist">
                                    <input type="hidden" name="checklist_id" value="<?php echo esc_attr($c->id); ?>">
                                    <button type="submit" class="button" onclick="return confirm('Archive?')">Archive</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Edit checklist page
     */
    private function edit_checklist_page($checklist_id) {
        global $wpdb;
        $checklists_table = $wpdb->prefix . 'customer_portal_checklists';
        $items_table = $wpdb->prefix . 'customer_portal_checklist_items';
        $users_table = $wpdb->prefix . 'customer_portal_users';
        
        $checklist = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.first_name, u.last_name FROM {$checklists_table} c
             LEFT JOIN {$users_table} u ON c.user_id = u.id
             WHERE c.id = %d",
            $checklist_id
        ));
        
        if (!$checklist) {
            echo '<div class="wrap"><h1>Checklist not found</h1></div>';
            return;
        }
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$items_table} WHERE checklist_id = %d ORDER BY sort_order ASC",
            $checklist_id
        ));
        
        ?>
        <div class="wrap">
            <h1>Edit: <?php echo esc_html($checklist->title); ?></h1>
            <p>Client: <strong><?php echo esc_html($checklist->first_name . ' ' . $checklist->last_name); ?></strong></p>
            <p><a href="?page=customer-portal-checklists">&larr; Back</a></p>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:20px 0;">
                <div style="background:white; padding:20px; border-radius:5px;">
                    <h2>Add Item</h2>
                    <form method="post">
                        <?php wp_nonce_field('cp_checklist_action', 'cp_checklist_nonce'); ?>
                        <input type="hidden" name="cp_action" value="add_item">
                        <input type="hidden" name="checklist_id" value="<?php echo esc_attr($checklist_id); ?>">
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="product_name" placeholder="Product name" style="flex:1;" required>
                            <button type="submit" class="button button-primary">Add</button>
                        </div>
                    </form>
                </div>
                
                <div style="background:white; padding:20px; border-radius:5px;">
                    <h2>Bulk Add</h2>
                    <form method="post">
                        <?php wp_nonce_field('cp_checklist_action', 'cp_checklist_nonce'); ?>
                        <input type="hidden" name="cp_action" value="bulk_add_items">
                        <input type="hidden" name="checklist_id" value="<?php echo esc_attr($checklist_id); ?>">
                        <?php if ($checklist->type === 'bagatinatajs'): ?>
                            <textarea name="bulk_text" rows="5" style="width:100%;" placeholder="[Store]&#10;Product | URL | Code"></textarea>
                        <?php else: ?>
                            <textarea name="bulk_text" rows="4" style="width:100%;" placeholder="One per line or comma-separated"></textarea>
                        <?php endif; ?>
                        <button type="submit" class="button button-primary" style="margin-top:10px;">Add All</button>
                    </form>
                </div>
            </div>
            
            <h2>Items (<?php echo count($items); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="40">#</th>
                        <?php if ($checklist->type === 'bagatinatajs'): ?>
                            <th width="100">Store</th>
                            <th>Product</th>
                            <th width="60">Link</th>
                            <th width="80">Code</th>
                        <?php else: ?>
                            <th>Product</th>
                        <?php endif; ?>
                        <th width="70">Status</th>
                        <th width="70">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="<?php echo $checklist->type === 'bagatinatajs' ? 7 : 4; ?>">No items yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td><?php echo ($i + 1); ?></td>
                            <?php if ($checklist->type === 'bagatinatajs'): ?>
                                <td><?php echo esc_html($item->store_name ?: '-'); ?></td>
                                <td><?php echo esc_html($item->product_name); ?></td>
                                <td><?php echo $item->link ? '<a href="' . esc_url($item->link) . '" target="_blank">→</a>' : '-'; ?></td>
                                <td><?php echo esc_html($item->discount_code ?: '-'); ?></td>
                            <?php else: ?>
                                <td><?php echo esc_html($item->product_name); ?></td>
                            <?php endif; ?>
                            <td><?php echo $item->is_checked ? '✓' : '○'; ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('cp_checklist_action', 'cp_checklist_nonce'); ?>
                                    <input type="hidden" name="cp_action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?php echo esc_attr($item->id); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Delete?')">×</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Links page
     */
    public function links_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }
        
        // Handle actions
        if (isset($_POST['cp_action']) && isset($_POST['cp_links_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_links_nonce'], 'cp_links_action')) {
                wp_die(__('Security check failed.'));
            }
            
            if ($_POST['cp_action'] === 'add_link' && isset($_POST['user_id'])) {
                $url = esc_url_raw($_POST['url']);
                if ($url) {
                    CP()->database->add_link(
                        intval($_POST['user_id']),
                        $url,
                        sanitize_text_field($_POST['description'])
                    );
                    echo '<div class="notice notice-success"><p>Link added!</p></div>';
                }
            }
            
            if ($_POST['cp_action'] === 'delete_link' && isset($_POST['link_id'])) {
                CP()->database->delete_link(intval($_POST['link_id']));
                echo '<div class="notice notice-success"><p>Link deleted!</p></div>';
            }
        }
        
        $users = CP()->database->get_active_users();
        $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        global $wpdb;
        $links_table = $wpdb->prefix . 'customer_portal_links';
        $links = array();
        
        if ($selected_user_id) {
            $links = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$links_table} WHERE user_id = %d ORDER BY sort_order ASC",
                $selected_user_id
            ));
        }
        
        ?>
        <div class="wrap">
            <h1>Links</h1>
            
            <div style="background:white; padding:20px; margin:20px 0; border-radius:5px;">
                <h2>Select Client</h2>
                <form method="get">
                    <input type="hidden" name="page" value="customer-portal-links">
                    <select name="user_id" onchange="this.form.submit()">
                        <option value="">Select client...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->id); ?>" <?php selected($selected_user_id, $user->id); ?>>
                                <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($selected_user_id): ?>
            <div style="background:white; padding:20px; margin:20px 0; border-radius:5px;">
                <h2>Add Link</h2>
                <form method="post">
                    <?php wp_nonce_field('cp_links_action', 'cp_links_nonce'); ?>
                    <input type="hidden" name="cp_action" value="add_link">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($selected_user_id); ?>">
                    <table class="form-table">
                        <tr>
                            <th><label>URL</label></th>
                            <td><input type="url" name="url" class="regular-text" placeholder="https://..." required></td>
                        </tr>
                        <tr>
                            <th><label>Description</label></th>
                            <td><input type="text" name="description" class="regular-text" placeholder="Link description" required></td>
                        </tr>
                    </table>
                    <p class="submit"><input type="submit" class="button button-primary" value="Add Link"></p>
                </form>
            </div>
            
            <h2>Links (<?php echo count($links); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="40">#</th>
                        <th>Description</th>
                        <th>URL</th>
                        <th width="80">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                        <tr><td colspan="4">No links yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($links as $i => $link): ?>
                        <tr>
                            <td><?php echo ($i + 1); ?></td>
                            <td><strong><?php echo esc_html($link->description); ?></strong></td>
                            <td><a href="<?php echo esc_url($link->url); ?>" target="_blank"><?php echo esc_html(substr($link->url, 0, 60)); ?></a></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('cp_links_action', 'cp_links_nonce'); ?>
                                    <input type="hidden" name="cp_action" value="delete_link">
                                    <input type="hidden" name="link_id" value="<?php echo esc_attr($link->id); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Delete?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>Select a client above to manage their links.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }
        
        if (isset($_POST['cp_save_settings']) && isset($_POST['cp_settings_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_settings_nonce'], 'cp_save_settings')) {
                wp_die(__('Security check failed.'));
            }
            
            // Only update Telegram options if not defined as constants in wp-config.php
            if (!CP()->is_bot_token_constant()) {
                update_option('cp_telegram_bot_token', sanitize_text_field($_POST['telegram_bot_token']));
            }
            if (!CP()->is_bot_username_constant()) {
                update_option('cp_telegram_bot_username', sanitize_text_field($_POST['telegram_bot_username']));
            }
            update_option('cp_google_client_id', sanitize_text_field($_POST['google_client_id']));
            update_option('cp_google_client_secret', sanitize_text_field($_POST['google_client_secret']));
            update_option('cp_google_refresh_token', sanitize_text_field($_POST['google_refresh_token']));
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <form method="post">
                <?php wp_nonce_field('cp_save_settings', 'cp_settings_nonce'); ?>
                
                <h2>Telegram Bot</h2>
                <?php if (CP()->is_bot_token_constant() || CP()->is_bot_username_constant()): ?>
                    <div class="notice notice-info inline">
                        <p><strong>Note:</strong> Telegram bot settings are defined in wp-config.php and cannot be edited here.</p>
                    </div>
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label>Bot Token</label></th>
                        <td>
                            <?php if (CP()->is_bot_token_constant()): ?>
                                <input type="text" value="<?php echo esc_attr(substr(CP()->get_bot_token(), 0, 20) . '... (defined in wp-config.php)'); ?>" class="regular-text" disabled>
                                <p class="description">Configured in wp-config.php constant CP_TELEGRAM_BOT_TOKEN</p>
                            <?php else: ?>
                                <input type="text" name="telegram_bot_token" value="<?php echo esc_attr(get_option('cp_telegram_bot_token')); ?>" class="regular-text">
                                <p class="description">Get from @BotFather</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Bot Username</label></th>
                        <td>
                            <?php if (CP()->is_bot_username_constant()): ?>
                                <input type="text" value="<?php echo esc_attr(CP()->get_bot_username() . ' (defined in wp-config.php)'); ?>" class="regular-text" disabled>
                                <p class="description">Configured in wp-config.php constant CP_TELEGRAM_BOT_USERNAME</p>
                            <?php else: ?>
                                <input type="text" name="telegram_bot_username" value="<?php echo esc_attr(get_option('cp_telegram_bot_username')); ?>" class="regular-text">
                                <p class="description">Without @</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <h2>Google Drive API</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Client ID</label></th>
                        <td><input type="text" name="google_client_id" value="<?php echo esc_attr(get_option('cp_google_client_id')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>Client Secret</label></th>
                        <td><input type="text" name="google_client_secret" value="<?php echo esc_attr(get_option('cp_google_client_secret')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>Refresh Token</label></th>
                        <td><input type="text" name="google_refresh_token" value="<?php echo esc_attr(get_option('cp_google_refresh_token')); ?>" class="regular-text"></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="cp_save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Calendar & Bookings page
     */
    public function calendar_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }

        // Enqueue calendar assets
        wp_enqueue_style(
            'cp-calendar',
            CP_PLUGIN_URL . 'assets/css/calendar.css',
            array(),
            CP_VERSION
        );

        wp_enqueue_script(
            'cp-calendar',
            CP_PLUGIN_URL . 'assets/js/calendar.js',
            array(),
            CP_VERSION,
            true
        );

        wp_localize_script('cp-calendar', 'cpCalendarConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cp_nonce'),
            'isAdmin' => true,
            'isCustomer' => false
        ));

        // Handle admin cancel booking
        if (isset($_POST['action']) && $_POST['action'] === 'admin_cancel_booking' && isset($_POST['cp_admin_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_admin_nonce'], 'cp_admin_action')) {
                wp_die(__('Security check failed.'));
            }

            $date = sanitize_text_field($_POST['date']);
            $hour = intval($_POST['hour']);

            $result = CP()->database->admin_cancel_booking($date, $hour);
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }

        // Get bookings for the list
        $bookings = CP()->database->get_all_bookings(array('upcoming_only' => true));

        ?>
        <div class="wrap">
            <h1>Calendar & Bookings</h1>

            <div class="cp-calendar-admin-section">
                <h2>Manage Availability</h2>
                <p>Click any time slot to toggle between <strong>free</strong> (green) and <strong>blocked</strong> (red).</p>
                <p><strong>Note:</strong> Booked slots (orange) cannot be toggled directly. Cancel the booking first.</p>

                <div class="calendar-legend" style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                    <strong>Legend:</strong>
                    <span style="margin-left: 20px;"><span style="display: inline-block; width: 15px; height: 15px; background: #27ae60; border-radius: 3px; margin-right: 5px;"></span> Free (Available)</span>
                    <span style="margin-left: 20px;"><span style="display: inline-block; width: 15px; height: 15px; background: #E87C52; border-radius: 3px; margin-right: 5px;"></span> Booked</span>
                    <span style="margin-left: 20px;"><span style="display: inline-block; width: 15px; height: 15px; background: #e74c3c; border-radius: 3px; margin-right: 5px;"></span> Blocked</span>
                    <span style="margin-left: 20px;"><span style="display: inline-block; width: 15px; height: 15px; background: #95a5a6; border-radius: 3px; margin-right: 5px;"></span> Past</span>
                </div>

                <div id="bc-calendar-container"></div>
            </div>

            <div class="cp-bookings-list-section" style="margin-top: 40px;">
                <h2>Upcoming Bookings</h2>

                <?php if (empty($bookings)): ?>
                    <p>No upcoming bookings.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Booked At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo esc_html(trim($booking->first_name . ' ' . $booking->last_name)); ?></td>
                                    <td><?php echo esc_html(date('F j, Y', strtotime($booking->slot_date))); ?></td>
                                    <td><?php echo esc_html(sprintf('%02d:00', $booking->slot_hour)); ?></td>
                                    <td><?php echo esc_html(date('F j, Y g:i A', strtotime($booking->booked_at))); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="admin_cancel_booking">
                                            <input type="hidden" name="date" value="<?php echo esc_attr($booking->slot_date); ?>">
                                            <input type="hidden" name="hour" value="<?php echo esc_attr($booking->slot_hour); ?>">
                                            <input type="hidden" name="cp_admin_nonce" value="<?php echo wp_create_nonce('cp_admin_action'); ?>">
                                            <button type="submit" class="button button-small" onclick="return confirm('Cancel this booking?');">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Surveys page
     */
    public function surveys_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }

        // Handle TXT export BEFORE any output
        if (isset($_GET['export']) && $_GET['export'] === 'txt' && isset($_GET['view']) && $_GET['view'] === 'supplement_feedback') {
            $selected_survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;
            $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

            if ($selected_survey_id && $selected_user_id) {
                $comments = CP()->database->get_user_supplement_comments($selected_user_id, $selected_survey_id);
                if (!empty($comments)) {
                    $users = CP()->database->get_active_users();
                    $user = array_filter($users, function($u) use ($selected_user_id) {
                        return $u->id == $selected_user_id;
                    });
                    $user = reset($user);

                    if ($user) {
                        $this->export_supplement_feedback_txt($selected_survey_id, $selected_user_id, $comments, $user);
                        exit;
                    }
                }
            }
        }

        $surveys_module = CP()->surveys;

        // Handle actions
        if (isset($_POST['cp_action']) && isset($_POST['cp_surveys_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_surveys_nonce'], 'cp_surveys_action')) {
                wp_die(__('Security check failed.'));
            }

            if ($_POST['cp_action'] === 'assign_survey' && isset($_POST['user_id']) && isset($_POST['survey_id'])) {
                $result = CP()->database->assign_survey(
                    intval($_POST['user_id']),
                    sanitize_text_field($_POST['survey_id'])
                );
                if ($result) {
                    echo '<div class="notice notice-success"><p>Survey assigned!</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>Survey already assigned to this user.</p></div>';
                }
            }

            if ($_POST['cp_action'] === 'remove_assignment' && isset($_POST['assignment_id'])) {
                CP()->database->remove_survey_assignment(intval($_POST['assignment_id']));
                echo '<div class="notice notice-success"><p>Assignment removed!</p></div>';
            }
        }

        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'assignments';

        ?>
        <div class="wrap">
            <h1>Surveys</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=customer-portal-surveys&view=assignments" class="nav-tab <?php echo $view === 'assignments' ? 'nav-tab-active' : ''; ?>">Assignments</a>
                <a href="?page=customer-portal-surveys&view=results" class="nav-tab <?php echo $view === 'results' ? 'nav-tab-active' : ''; ?>">Results</a>
                <a href="?page=customer-portal-surveys&view=supplement_surveys" class="nav-tab <?php echo $view === 'supplement_surveys' ? 'nav-tab-active' : ''; ?>">Supplement Surveys</a>
                <a href="?page=customer-portal-surveys&view=supplement_feedback" class="nav-tab <?php echo $view === 'supplement_feedback' ? 'nav-tab-active' : ''; ?>">Client Feedback</a>
            </h2>

            <?php if ($view === 'assignments'): ?>
                <?php $this->surveys_assignments_view(); ?>
            <?php elseif ($view === 'results'): ?>
                <?php $this->surveys_results_view(); ?>
            <?php elseif ($view === 'supplement_surveys'): ?>
                <?php $this->supplement_surveys_view(); ?>
            <?php elseif ($view === 'supplement_feedback'): ?>
                <?php $this->supplement_feedback_view(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Surveys assignments view
     */
    private function surveys_assignments_view() {
        $surveys_module = CP()->surveys;
        $available_surveys = $surveys_module->get_available_surveys();

        // Add supplement surveys from database
        $supplement_surveys = CP()->database->get_supplement_surveys();
        foreach ($supplement_surveys as $survey) {
            $available_surveys['supplement_' . $survey->id] = array(
                'id' => 'supplement_' . $survey->id,
                'title' => $survey->title,
                'type' => 'supplement_feedback'
            );
        }

        $users = CP()->database->get_active_users();
        $assignments = CP()->database->get_all_survey_assignments();

        ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px;">
            <h2>Assign Survey to Client</h2>
            <form method="post">
                <?php wp_nonce_field('cp_surveys_action', 'cp_surveys_nonce'); ?>
                <input type="hidden" name="cp_action" value="assign_survey">
                <table class="form-table">
                    <tr>
                        <th><label>Client</label></th>
                        <td>
                            <select name="user_id" required style="min-width: 250px;">
                                <option value="">Select client...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo esc_attr($user->id); ?>">
                                        <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Survey</label></th>
                        <td>
                            <select name="survey_id" required style="min-width: 250px;">
                                <option value="">Select survey...</option>
                                <?php foreach ($available_surveys as $survey): ?>
                                    <option value="<?php echo esc_attr($survey['id']); ?>">
                                        <?php echo esc_html($survey['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="Assign Survey"></p>
            </form>
        </div>

        <h2>Current Assignments</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Survey</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="5">No assignments yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                    <?php
                        $survey_info = isset($available_surveys[$assignment->survey_id]) ? $available_surveys[$assignment->survey_id] : null;
                        $survey_title = $survey_info ? $survey_info['title'] : $assignment->survey_id;
                    ?>
                    <tr>
                        <td><?php echo esc_html(trim($assignment->first_name . ' ' . $assignment->last_name)); ?></td>
                        <td><?php echo esc_html($survey_title); ?></td>
                        <td><?php echo esc_html(ucfirst($assignment->status)); ?></td>
                        <td><?php echo esc_html($assignment->created_at); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('cp_surveys_action', 'cp_surveys_nonce'); ?>
                                <input type="hidden" name="cp_action" value="remove_assignment">
                                <input type="hidden" name="assignment_id" value="<?php echo esc_attr($assignment->id); ?>">
                                <button type="submit" class="button button-small" onclick="return confirm('Remove this assignment?');">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Surveys results view
     */
    private function surveys_results_view() {
        $surveys_module = CP()->surveys;
        $available_surveys = $surveys_module->get_available_surveys();

        // Handle view details
        if (isset($_GET['result_id'])) {
            $this->survey_result_detail(intval($_GET['result_id']));
            return;
        }

        $results = CP()->database->get_all_survey_results();

        ?>
        <h2>Survey Results</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Client</th>
                    <th>Survey</th>
                    <th width="100">Total Score</th>
                    <th width="150">Submitted</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="6">No results yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $result): ?>
                    <?php
                        $survey_info = isset($available_surveys[$result->survey_id]) ? $available_surveys[$result->survey_id] : null;
                        $survey_title = $survey_info ? $survey_info['title'] : $result->survey_id;
                        $interpretation = $surveys_module->get_score_interpretation($result->total_score);
                    ?>
                    <tr>
                        <td><?php echo esc_html($result->id); ?></td>
                        <td><?php echo esc_html(trim($result->first_name . ' ' . $result->last_name)); ?></td>
                        <td><?php echo esc_html($survey_title); ?></td>
                        <td>
                            <strong><?php echo esc_html($result->total_score); ?></strong>
                            <br>
                            <span style="font-size: 11px; color: #666;"><?php echo esc_html($interpretation['label']); ?></span>
                        </td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($result->created_at))); ?></td>
                        <td>
                            <a href="?page=customer-portal-surveys&view=results&result_id=<?php echo $result->id; ?>" class="button button-small">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Survey result detail view
     */
    private function survey_result_detail($result_id) {
        $surveys_module = CP()->surveys;
        $result = CP()->database->get_survey_result($result_id);

        if (!$result) {
            echo '<p>Result not found.</p>';
            return;
        }

        $survey = $surveys_module->get_survey_definition($result->survey_id);
        $answers = json_decode($result->answers_json, true);
        $dimension_scores = json_decode($result->dimension_scores_json, true);
        $interpretation = $surveys_module->get_score_interpretation($result->total_score);

        ?>
        <p><a href="?page=customer-portal-surveys&view=results">&larr; Back to Results</a></p>

        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px;">
            <h2><?php echo esc_html($survey['title']); ?></h2>
            <p>
                <strong>Client:</strong> <?php echo esc_html(trim($result->first_name . ' ' . $result->last_name)); ?><br>
                <strong>Submitted:</strong> <?php echo esc_html(date('F j, Y g:i A', strtotime($result->created_at))); ?>
            </p>

            <h3>Overall Score: <?php echo esc_html($result->total_score); ?> - <?php echo esc_html($interpretation['label']); ?></h3>
            <p style="color: #666;"><?php echo esc_html($interpretation['description']); ?></p>

            <h3>Dimension Scores</h3>
            <table class="widefat" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Dimension</th>
                        <th width="100">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($survey['dimensions'] as $dim_key => $dim_label): ?>
                    <tr>
                        <td><?php echo esc_html($dim_label); ?></td>
                        <td><strong><?php echo isset($dimension_scores[$dim_key]) ? esc_html($dimension_scores[$dim_key]) : 0; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Answers</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th width="40">#</th>
                        <th>Question</th>
                        <th width="250">Answer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $q_num = 1; ?>
                    <?php foreach ($survey['questions'] as $question): ?>
                    <?php
                        $q_id = $question['id'];
                        $answer_value = isset($answers[$q_id]) ? $answers[$q_id] : '';
                        $answer_display = '';

                        if ($question['type'] === 'slider') {
                            $answer_display = $answer_value . ' / ' . $question['max'];
                        } elseif ($question['type'] === 'single_choice') {
                            foreach ($question['options'] as $option) {
                                if ($option['value'] === $answer_value) {
                                    $answer_display = $option['label'];
                                    break;
                                }
                            }
                        } elseif ($question['type'] === 'text') {
                            $answer_display = $answer_value;
                        }
                    ?>
                    <tr>
                        <td><?php echo $q_num++; ?></td>
                        <td><?php echo esc_html($question['label']); ?></td>
                        <td><?php echo esc_html($answer_display); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Supplement Surveys Management View
     */
    private function supplement_surveys_view() {
        // Handle create/edit/delete/assign actions
        if (isset($_POST['cp_action']) && isset($_POST['cp_supplement_nonce'])) {
            if (!wp_verify_nonce($_POST['cp_supplement_nonce'], 'cp_supplement_action')) {
                wp_die(__('Security check failed.'));
            }

            if ($_POST['cp_action'] === 'save_survey') {
                $survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
                $title = sanitize_text_field($_POST['survey_title']);
                $supplements_text = sanitize_textarea_field($_POST['supplements_list']);

                // Parse supplements by newline
                $supplements = array_filter(array_map('trim', explode("\n", $supplements_text)));

                $saved_id = CP()->database->save_supplement_survey($survey_id, $title, $supplements);

                if ($saved_id) {
                    echo '<div class="notice notice-success"><p>Survey saved successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Error saving survey. A survey with this title already exists.</p></div>';
                }
            }

            if ($_POST['cp_action'] === 'delete_survey' && isset($_POST['survey_id'])) {
                CP()->database->delete_supplement_survey(intval($_POST['survey_id']));
                echo '<div class="notice notice-success"><p>Survey deleted!</p></div>';
            }

            if ($_POST['cp_action'] === 'assign_survey' && isset($_POST['survey_id']) && isset($_POST['user_id'])) {
                $survey_id = intval($_POST['survey_id']);
                $user_id = intval($_POST['user_id']);

                // Use format "supplement_{id}" for survey_id in assignments table
                $assignment_survey_id = 'supplement_' . $survey_id;

                $result = CP()->database->assign_survey($user_id, $assignment_survey_id);

                if ($result) {
                    echo '<div class="notice notice-success"><p>Survey assigned to user!</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>Survey already assigned to this user.</p></div>';
                }
            }
        }

        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_survey = null;
        $supplements_text = '';

        if ($edit_id) {
            $edit_survey = CP()->database->get_supplement_survey($edit_id);
            if ($edit_survey && !empty($edit_survey->supplements)) {
                $supplements_text = implode("\n", array_map(function($s) { return $s->name; }, $edit_survey->supplements));
            }
        }

        $surveys = CP()->database->get_supplement_surveys();

        ?>
        <div class="cp-supplement-surveys">
            <h2><?php echo $edit_id ? 'Edit Survey' : 'Create New Survey'; ?></h2>

            <form method="post" style="max-width: 600px;">
                <?php wp_nonce_field('cp_supplement_action', 'cp_supplement_nonce'); ?>
                <input type="hidden" name="cp_action" value="save_survey">
                <?php if ($edit_id): ?>
                    <input type="hidden" name="survey_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th><label for="survey_title">Survey Title</label></th>
                        <td>
                            <input type="text" id="survey_title" name="survey_title"
                                   value="<?php echo $edit_survey ? esc_attr($edit_survey->title) : ''; ?>"
                                   class="regular-text" required>
                            <p class="description">E.g., "Urīnskābes mazināšanas protokolam"</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="supplements_list">Supplements List</label></th>
                        <td>
                            <textarea id="supplements_list" name="supplements_list" rows="15"
                                      class="large-text code" required><?php echo esc_textarea($supplements_text); ?></textarea>
                            <p class="description">One supplement per line</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $edit_id ? 'Update Survey' : 'Create Survey'; ?>
                    </button>
                    <?php if ($edit_id): ?>
                        <a href="?page=customer-portal-surveys&view=supplement_surveys" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>

            <hr style="margin: 40px 0;">

            <h2>Existing Surveys</h2>
            <?php if (empty($surveys)): ?>
                <p>No supplement surveys yet.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Supplements Count</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($surveys as $survey): ?>
                            <?php
                            $survey_data = CP()->database->get_supplement_survey($survey->id);
                            $supplement_count = count($survey_data->supplements ?? []);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($survey->title); ?></strong></td>
                                <td><?php echo $supplement_count; ?> supplements</td>
                                <td><?php echo date('F j, Y', strtotime($survey->created_at)); ?></td>
                                <td>
                                    <a href="?page=customer-portal-surveys&view=supplement_surveys&edit=<?php echo $survey->id; ?>"
                                       class="button button-small">Edit</a>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('cp_supplement_action', 'cp_supplement_nonce'); ?>
                                        <input type="hidden" name="cp_action" value="delete_survey">
                                        <input type="hidden" name="survey_id" value="<?php echo $survey->id; ?>">
                                        <button type="submit" class="button button-small"
                                                onclick="return confirm('Delete this survey? All comments will be lost.');">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Client Supplement Feedback View
     */
    private function supplement_feedback_view() {
        $surveys = CP()->database->get_supplement_surveys();

        if (empty($surveys)) {
            echo '<p>No supplement surveys created yet. <a href="?page=customer-portal-surveys&view=supplement_surveys">Create one</a></p>';
            return;
        }

        $selected_survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : $surveys[0]->id;
        $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        $commenters = CP()->database->get_survey_commenters($selected_survey_id);
        $comments = [];

        if ($selected_user_id) {
            $comments = CP()->database->get_user_supplement_comments($selected_user_id, $selected_survey_id);
            $user = CP()->database->get_active_users();
            $user = array_filter($user, function($u) use ($selected_user_id) {
                return $u->id == $selected_user_id;
            });
            $user = reset($user);
        }

        ?>
        <div class="cp-supplement-feedback">
            <h2>Client Supplement Feedback</h2>

            <form method="get" style="margin-bottom: 20px; background: #f9f9f9; padding: 15px; border: 1px solid #ddd;">
                <input type="hidden" name="page" value="customer-portal-surveys">
                <input type="hidden" name="view" value="supplement_feedback">

                <table class="form-table">
                    <tr>
                        <th><label>Select Survey</label></th>
                        <td>
                            <select name="survey_id" onchange="this.form.submit()">
                                <?php foreach ($surveys as $survey): ?>
                                    <option value="<?php echo $survey->id; ?>"
                                            <?php selected($selected_survey_id, $survey->id); ?>>
                                        <?php echo esc_html($survey->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Select Client</label></th>
                        <td>
                            <select name="user_id" onchange="this.form.submit()">
                                <option value="">-- Select a client --</option>
                                <?php foreach ($commenters as $commenter): ?>
                                    <option value="<?php echo $commenter->id; ?>"
                                            <?php selected($selected_user_id, $commenter->id); ?>>
                                        <?php echo esc_html(trim($commenter->first_name . ' ' . $commenter->last_name)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </form>

            <?php if ($selected_user_id && !empty($comments)): ?>
                <div style="margin-bottom: 20px;">
                    <a href="?page=customer-portal-surveys&view=supplement_feedback&survey_id=<?php echo $selected_survey_id; ?>&user_id=<?php echo $selected_user_id; ?>&export=txt"
                       class="button button-primary">
                        Export to TXT
                    </a>
                </div>

                <h3>Feedback from <?php echo esc_html(trim($user->first_name . ' ' . $user->last_name)); ?></h3>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="30%">Supplement Name</th>
                            <th width="60%">Comment</th>
                            <th width="10%">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td><strong><?php echo esc_html($comment->supplement_name); ?></strong></td>
                                <td><?php echo nl2br(esc_html($comment->comment_text)); ?></td>
                                <td><?php echo date('M j, Y', strtotime($comment->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($selected_user_id): ?>
                <p>This client hasn't added any comments yet.</p>
            <?php else: ?>
                <p>Select a client to view their feedback.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Export supplement feedback to TXT
     */
    private function export_supplement_feedback_txt($survey_id, $user_id, $comments, $user) {
        $survey = CP()->database->get_supplement_survey($survey_id);
        $client_name = trim($user->first_name . ' ' . $user->last_name);

        $filename = 'supplement-feedback-' . sanitize_file_name($client_name) . '-' . date('Y-m-d') . '.txt';

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "Klients: " . $client_name . "\n";
        echo "Survey: " . $survey->title . "\n";
        echo "\n";
        echo str_repeat("=", 60) . "\n";
        echo "\n";

        foreach ($comments as $comment) {
            echo "[" . $comment->supplement_name . "]\n";
            echo $comment->comment_text . "\n";
            echo "\n";
        }

        echo str_repeat("=", 60) . "\n";
        echo "Exported: " . date('Y-m-d H:i:s') . "\n";
    }

    /**
     * Show supplement survey assignment UI
     */
    private function show_supplement_assignment_ui($survey_id) {
        $survey = CP()->database->get_supplement_survey($survey_id);

        if (!$survey) {
            echo '<p>Survey not found.</p>';
            return;
        }

        $users = CP()->database->get_active_users();
        $assignment_survey_id = 'supplement_' . $survey_id;

        // Get existing assignments for this survey
        $all_assignments = CP()->database->get_all_survey_assignments();
        $assigned_user_ids = array();
        foreach ($all_assignments as $assignment) {
            if ($assignment->survey_id === $assignment_survey_id) {
                $assigned_user_ids[] = $assignment->user_id;
            }
        }

        ?>
        <div class="wrap">
            <h2>Assign Survey: <?php echo esc_html($survey->title); ?></h2>
            <p><a href="?page=customer-portal-surveys&view=supplement_surveys" class="button">← Back to Surveys</a></p>

            <h3>Assign to Users</h3>

            <form method="post" style="max-width: 600px;">
                <?php wp_nonce_field('cp_supplement_action', 'cp_supplement_nonce'); ?>
                <input type="hidden" name="cp_action" value="assign_survey">
                <input type="hidden" name="survey_id" value="<?php echo $survey_id; ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="user_id">Select User</label></th>
                        <td>
                            <select name="user_id" id="user_id" required>
                                <option value="">-- Select a user --</option>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $already_assigned = in_array($user->id, $assigned_user_ids);
                                    $disabled = $already_assigned ? 'disabled' : '';
                                    $suffix = $already_assigned ? ' (already assigned)' : '';
                                    ?>
                                    <option value="<?php echo $user->id; ?>" <?php echo $disabled; ?>>
                                        <?php echo esc_html(trim($user->first_name . ' ' . $user->last_name)) . $suffix; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Assign Survey</button>
                </p>
            </form>

            <hr style="margin: 40px 0;">

            <h3>Currently Assigned Users</h3>
            <?php if (empty($assigned_user_ids)): ?>
                <p>No users assigned yet.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Assigned Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_assignments as $assignment): ?>
                            <?php if ($assignment->survey_id === $assignment_survey_id): ?>
                                <tr>
                                    <td><?php echo esc_html(trim($assignment->first_name . ' ' . $assignment->last_name)); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($assignment->created_at)); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('cp_surveys_action', 'cp_surveys_nonce'); ?>
                                            <input type="hidden" name="cp_action" value="remove_assignment">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment->id; ?>">
                                            <button type="submit" class="button button-small"
                                                    onclick="return confirm('Remove this assignment?');">
                                                Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
