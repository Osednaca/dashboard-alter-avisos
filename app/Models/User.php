<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Businesses\Models\Business;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'job_title', 'avatar_path', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_users')
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk(config('signage.media_disk'))->url($this->avatar_path)
            : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
