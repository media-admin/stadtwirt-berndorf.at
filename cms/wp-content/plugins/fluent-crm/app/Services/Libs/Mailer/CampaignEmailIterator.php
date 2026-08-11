<?php

namespace FluentCrm\App\Services\Libs\Mailer;

use FluentCrm\App\Models\CampaignEmail;

/**
 * @deprecated This iterator has no in-tree callers and predates the transactional
 * claim pipeline in Handler/BaseHandler (atomic claims with claim tokens). Do not
 * build on it — use the Handler pipeline instead. Kept only for back-compat with
 * unknown external callers; scheduled for removal.
 */
class CampaignEmailIterator implements \Iterator
{
    protected $key = 0;
    protected $limit = 0;
    protected $offset = 0;
    protected $emails = null;
    protected $campaignId = null;

    public function __construct($campaignId = null, $limit = 10)
    {
        $this->campaignId = $campaignId;
        $this->limit = $limit ? $limit : 10;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->emails;
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->key++;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->offset = $this->offset;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->offset = 0;
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        $currentTime = current_time('mysql');

        $emails = CampaignEmail::whereIn('status', ['pending', 'scheduled'])
            ->when($this->campaignId, function ($query) {
                return $query->where('campaign_id', $this->campaignId);
            })
            ->where('scheduled_at', '<=', $currentTime)
            ->whereNotNull('scheduled_at')
            ->with('campaign', 'subscriber')
            ->orderBy('scheduled_at', 'ASC')
            ->offset($this->offset)
            ->limit($this->limit)
            ->get();

        $ids = $emails->pluck('id')->toArray();

        if ($ids) {
            // Claim guarded by current status so a row a concurrent sender already
            // claimed is not re-claimed, and WITHOUT touching scheduled_at (the old
            // UPDATE overwrote each row's schedule with "now").
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $query = "UPDATE {$wpdb->prefix}fc_campaign_emails SET status = %s, updated_at = %s WHERE status IN ('pending', 'scheduled') AND id IN ($placeholders)";
            $wpdb->query($wpdb->prepare($query, array_merge(['processing', $currentTime], $ids)));
        }

        $this->emails = $emails;

        return !$this->emails->isEmpty();
    }
}
