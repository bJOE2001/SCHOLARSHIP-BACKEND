<?php

namespace App\Models;

use Database\Factories\GrantAnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrantAnnouncement extends Model
{
    /** @use HasFactory<GrantAnnouncementFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grant_batch_id',
        'created_by_id',
        'title',
        'message',
        'program_name',
        'semester',
        'school_year',
        'venue',
        'total_beneficiaries',
        'created_by_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_beneficiaries' => 'integer',
        ];
    }

    /**
     * Get the grant batch linked to this announcement.
     *
     * @return BelongsTo<GrantBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(GrantBatch::class, 'grant_batch_id');
    }

    /**
     * Get the user who created this announcement.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
