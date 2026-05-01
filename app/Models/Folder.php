<?php
namespace App\Models;

use App\Models\File;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    protected $fillable = ['name'];

    public function file()
    {
        return $this->hasMany(File::class);
    }

}
