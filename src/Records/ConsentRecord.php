<?php

namespace Goldnead\StatamicConsent\Records;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One recorded decision.
 *
 * @property string $consent_id
 * @property int $version
 * @property array<int, string> $granted
 * @property string $how
 * @property string|null $site
 * @property Carbon $decided_at
 */
class ConsentRecord extends Model
{
    protected $table = 'consent_records';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'granted' => 'array',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * The question this table exists to answer: what did this person consent to?
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForConsentId(Builder $query, string $id): Builder
    {
        return $query->where('consent_id', $id)->orderByDesc('decided_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOlderThan(Builder $query, \DateTimeInterface $cutoff): Builder
    {
        return $query->where('decided_at', '<', $cutoff);
    }
}
