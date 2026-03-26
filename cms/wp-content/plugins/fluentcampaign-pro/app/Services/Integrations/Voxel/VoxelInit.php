<?php

namespace FluentCampaign\App\Services\Integrations\Voxel;

use FluentCrm\Framework\Support\Arr;

class VoxelInit
{
    public function init()
    {
        new \FluentCampaign\App\Services\Integrations\Voxel\VoxelOrderPlacedTrigger();

        add_filter('fluent_crm/purchase_history_providers', [$this, 'registerPurchaseHistoryProvider']);
        add_filter('fluent_crm/purchase_history_voxel', [$this, 'voxelOrders'], 10, 2);

        add_filter('fluent_crm/funnel_icons', [$this, 'addVoxelIcon'], 10 , 1);

    }

    public function registerPurchaseHistoryProvider($providers)
    {
        $providers['voxel'] = [
            'title' => __('Voxel Purchase History', 'fluentcampaign-pro'),
            'name' => __('Voxel', 'fluentcampaign-pro'),
        ];

        return $providers;
    }

    public function voxelOrders($data, $subscriber)
    {
        if (!$subscriber->user_id) {
            return $data;
        }

        $page    = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
        $perPage = isset($_REQUEST['per_page']) ? $_REQUEST['per_page'] : 10;

        $sort_by   = sanitize_sql_orderby($_REQUEST['sort_by']);
        $sort_type = sanitize_sql_orderby($_REQUEST['sort_type']);

        $valid_columns = ['id', 'created_at'];
        $valid_directions = ['ASC', 'DESC'];

        if (!in_array($sort_by, $valid_columns)) {
            $sort_by = 'id';
        }
        if (!in_array(strtoupper($sort_type), $valid_directions)) {
            $sort_type = 'DESC';
        }

        $query = fluentCrmDb()->table('vx_orders')
                              ->where('customer_id', $subscriber->user_id)
                              ->orderBy($sort_by, $sort_type);

        // Get total count before pagination
        $totalCount = $query->count();
        
        // Calculate total pages
        $totalPages = ceil($totalCount / $perPage);

        // Get paginated results
        $vxOrders = $query->offset(($page - 1) * $perPage)
                          ->limit($perPage)
                          ->get();

        $orders = [];
        foreach ($vxOrders as $vxOrder) {
            $orderActionHtml = '<a target="_blank" href="' . add_query_arg('id', $vxOrder->id, admin_url('admin.php?page=voxel-orders&order_id='.$vxOrder->id)) . '">' . __('View Order', 'fluent-crm') . '</a>';

            $details = json_decode($vxOrder->details, true);
            $orders[] = [
                'order'  => '#' . $vxOrder->id,
                'date'   => date_i18n(get_option('date_format'), strtotime($vxOrder->created_at)),
                'total'  => \Voxel\currency_format( intval($details['pricing']['total'] * 100), $details['pricing']['currency'] ),
                'status' => $vxOrder->status,
                'action' => $orderActionHtml
            ];
        }

        $posts = get_posts([
            'author'        => $subscriber->user_id,
            'post_type'     => 'profile',
            'numberposts'   => 1, // Get only one post
        ]);
        $profileUrl = admin_url('post.php?post='.$posts[0]->ID.'&action=edit');

        $returnData = [
            'data'           => $orders,
            'total'          => $totalCount,
            'per_page'       => $perPage,
            'current_page'   => $page,
            'last_page'      => $totalPages,
            'columns_config' => [
                'order'  => [
                    'label'    => __('Order', 'fluent-crm'),
                    'width'    => '100px',
                    'sortable' => true,
                    'key'      => 'id'
                ],
                'date'   => [
                    'label'    => __('Date', 'fluent-crm'),
                    'sortable' => true,
                    'key'      => 'created_at'
                ],
                'status' => [
                    'label'    => __('Status', 'fluent-crm'),
                    'width'    => '140px',
                    'sortable' => false
                ],
                'total'  => [
                    'label'    => __('Total', 'fluent-crm'),
                    'width'    => '120px',
                    'sortable' => false,
                    'key'      => 'total'
                ],
                'action' => [
                    'label'    => __('Actions', 'fluent-crm'),
                    'width'    => '100px',
                    'sortable' => false
                ]
            ]
        ];

        if (!empty($posts) && $orders) {
            $returnData['after_html'] = '<p><a target="_blank" rel="noopener" href="'.$profileUrl.'">' . esc_html__('View Customer Profile', 'fluent-crm') . '</a></p>';
        }

        return $returnData;

    }

    public function addVoxelIcon($icons)
    {
        $icons['voxel'] = '<svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 206.34 206.34">
                            <polygon points="114.63 91.71 114.63 137.56 22.93 45.85 45.85 22.93 22.93 22.93 0 45.85 0 114.63 91.71 206.34 114.63 206.34 114.63 160.49 137.56 160.49 206.34 91.71 206.34 22.93 183.41 22.93 114.63 91.71" fill="#909399"></polygon>
                            <polygon points="160.49 0 68.78 0 45.85 22.93 183.41 22.93 160.49 0" fill="#909399"></polygon>
                            </svg>';
        return $icons;
    }

}