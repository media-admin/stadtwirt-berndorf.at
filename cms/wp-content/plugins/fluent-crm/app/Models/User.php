<?php

namespace FluentCrm\App\Models;

/**
 *  User Model - DB Model for WordPress Users Table
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.0.0
 */
class User extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'ID';

    protected $hidden = ['user_pass', 'user_activation_key'];

    protected $appends = [ 'photo'];

    /**
     * Accessor to get dynamic photo attribute
     * @return string
     */
    public function getPhotoAttribute()
    {
        $contact = Subscriber::where('user_id', $this->ID);

        if(!empty($this->attributes['user_email'])) {
            $contact->orWhere('email', $this->attributes['user_email']);
        }

        $contact = $contact->first();

        if($contact) {
            return $contact->photo;
        }

        if(empty($this->attributes['user_email'])) {
            return '';
        }

        return fluentcrmGravatar($this->attributes['user_email'], $this->attributes['display_name']);
    }
}
