<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Departments extends Model
{
    /**
     * The database table may be used by the model.
     *
     * @var string
     */
    protected $table = 'departments';

    /**
     * The that no timestamp are used.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'region_code', 'code', 'name', 'slug',
    ];

    /**
     * Get the record of the region.
     *
     * @return \App\Regions
     */
    public function region()
    {
        return $this->belongsTo('App\Regions', 'region_code', 'code');
    }

    /**
     * Get the record of the cities.
     *
     * @return \App\Cities
     */
    public function cities()
    {
        return $this->hasMany('App\Cities', 'code', 'department_code');
    }
}
