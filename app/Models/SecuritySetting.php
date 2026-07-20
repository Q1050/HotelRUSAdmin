<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SecuritySetting extends Model{protected $primaryKey='key';public $incrementing=false;protected $keyType='string';protected $fillable=['key','value'];public static function valueOf(string $key,string $default):string{return static::whereKey($key)->value('value')??$default;}}
