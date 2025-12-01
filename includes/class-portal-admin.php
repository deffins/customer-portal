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
                        <th>Drive Folder</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7">No users yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->telegram_id); ?></td>
                            <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
                            <td><?php echo esc_html($user->username ? '@' . $user->username : '-'); ?></td>
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
            
            update_option('cp_telegram_bot_token', sanitize_text_field($_POST['telegram_bot_token']));
            update_option('cp_telegram_bot_username', sanitize_text_field($_POST['telegram_bot_username']));
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
                <table class="form-table">
                    <tr>
                        <th><label>Bot Token</label></th>
                        <td>
                            <input type="text" name="telegram_bot_token" value="<?php echo esc_attr(get_option('cp_telegram_bot_token')); ?>" class="regular-text">
                            <p class="description">Get from @BotFather</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Bot Username</label></th>
                        <td>
                            <input type="text" name="telegram_bot_username" value="<?php echo esc_attr(get_option('cp_telegram_bot_username')); ?>" class="regular-text">
                            <p class="description">Without @</p>
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
}
