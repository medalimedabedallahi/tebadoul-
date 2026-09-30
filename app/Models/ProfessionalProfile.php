<?php

namespace App\Models;

use App\Actions\Profiles\SaveProfessionalProfile;
use Database\Factories\ProfessionalProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Professional profile of an account, written only by {@see SaveProfessionalProfile}, which keeps
 * the references consistent (profession of the sector, establishment in the moughataa...).
 *
 * @property int $id
 * @property int $user_id
 * @property int $sector_id
 * @property int $profession_id
 * @property int|null $specialty_id
 * @property int|null $grade_id
 * @property int $moughataa_id
 * @property int|null $establishment_id
 * @property string|null $professional_identifier
 */
#[Fillable(['sector_id', 'profession_id', 'specialty_id', 'grade_id', 'moughataa_id', 'establishment_id', 'professional_identifier'])]
#[Hidden(['professional_identifier'])]
class ProfessionalProfile extends Model
{
    /** @use HasFactory<ProfessionalProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'professional_identifier' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<Profession, $this>
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    /**
     * @return BelongsTo<Specialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Moughataa, $this>
     */
    public function moughataa(): BelongsTo
    {
        return $this->belongsTo(Moughataa::class);
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
