<?php

namespace Arifur\BookstackBackup\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
	protected $table = 'backups';

	protected $fillable = [
		'title',
		'file_path',
		'created_by',
        'sha_hash',
		'status',
		'downloaded_by',
		'deleted_by',
	];
}
