<?php

namespace FluentCrm\App\Models;

/**
 *  Funnel Model - DB Model for Automation Funnels
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.0.0
 */
class Funnel extends Model
{
    private static $type = 'funnels';

    protected $table = 'fc_funnels';

    protected $fillable = [
        'type',
        'title',
        'trigger_name',
        'status',
        'conditions',
        'settings',
        'created_by',
        'updated_at'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->type = self::$type;
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('fc_funnels.type', '=', self::$type);
        });
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function actions()
    {
        return $this->hasMany(
            __NAMESPACE__ . '\FunnelSequence', 'funnel_id', 'id'
        );
    }

    public function subscribers()
    {
        return $this->hasMany(
            __NAMESPACE__ . '\FunnelSubscriber', 'funnel_id', 'id'
        );
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($settings)
    {
        return \maybe_unserialize($settings);
    }

    public function setConditionsAttribute($conditions)
    {
        $this->attributes['conditions'] = \maybe_serialize($conditions);
    }

    public function getConditionsAttribute($conditions)
    {
        return \maybe_unserialize($conditions);
    }

    public function getSubscribersCount()
    {
        return $this->subscribers()->count();
    }

    public function updateMeta($key, $value)
    {
        fluentcrm_update_meta($this->id, __CLASS__, $key, $value);
    }

    public function getMeta($key, $default = '')
    {
        $meta = fluentcrm_get_meta($this->id, __CLASS__, $key);
        if($meta) {
            return $meta->value;
        }
        return $default;
    }

    public function deleteMeta($key)
    {
        fluentcrm_delete_meta($this->id, __CLASS__, $key);
    }

    public function labelsTerm()
    {
        return $this->belongsToMany(Label::class, 'fc_term_relations', 'object_id', 'term_id')
            ->wherePivot('object_type', __CLASS__);
    }

    public function labels()
    {
        $labelIds = TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->pluck('term_id')
            ->toArray();
        return Label::whereIn('id', $labelIds)->get();
    }

    public function getFormattedLabels()
    {
        $labels = $this->labels();
        return $labels->map(function ($label) {
            return [
                'id' => $label->id,
                'slug' => $label->slug,
                'title' => $label->title,
                'color' => $label->settings['color'] ?? ''
            ];
        });
    }

    public function attachLabels($labelIds)
    {
        if (!is_array($labelIds)) {
            $labelIds = [$labelIds];
        }

        $existingLabelIds = TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->pluck('term_id')
            ->toArray();

        $newLabelIds = array_diff($labelIds, $existingLabelIds);

        if (!empty($newLabelIds)) {
            foreach ($newLabelIds as $labelId) {
                TermRelation::create([
                    'object_id' => $this->id,
                    'object_type' => __CLASS__,
                    'term_id' => $labelId
                ]);
            }
        }

        return $this;
    }

    /**
     * Replace existing funnel labels with the provided label IDs.
     *
     * @param array|int $labelIds
     * @return $this
     */
    public function syncLabels($labelIds)
    {
        if (!is_array($labelIds)) {
            $labelIds = [$labelIds];
        }

        $existingLabelIds = TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->pluck('term_id')
            ->toArray();

        $labelIds = array_unique(array_filter(array_map('intval', $labelIds)));
        $labelsToDetach = array_diff($existingLabelIds, $labelIds);
        $labelsToAttach = array_diff($labelIds, $existingLabelIds);

        if (!empty($labelsToDetach)) {
            $this->detachLabels($labelsToDetach);
        }

        if (!empty($labelsToAttach)) {
            $this->attachLabels($labelsToAttach);
        }

        return $this;
    }

    public function detachLabels($labelIds)
    {
        if (!is_array($labelIds)) {
            $labelIds = [$labelIds];
        }

        TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->whereIn('term_id', $labelIds)
            ->delete();

        return $this;
    }
}
