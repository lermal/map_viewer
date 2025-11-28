<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenderPage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'json_path',
        'description',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
