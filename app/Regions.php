<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Regions extends Model
{
    /**
     * The database table may be used by the model.
     *
     * @var string
     */
    protected $table = 'regions';

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
        'code', 'name', 'slug',
    ];

    /**
     * Get the record of the departments.
     *
     * @return \App\Departments
     */
    public function departments()
    {
        return $this->hasMany('App\Departments', 'code', 'regions_code');
    }
}
