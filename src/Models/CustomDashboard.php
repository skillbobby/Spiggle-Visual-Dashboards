<?php

namespace Spiggle\VisualDashboards\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Database\Factories\CustomDashboardFactory;
use Spiggle\VisualDashboards\Support\DashboardAccess;

class CustomDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'heading',
        'description',
        'is_default',
        'is_published',
        'viewer_roles',
        'layout_json',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_published' => 'boolean',
        'viewer_roles' => 'array',
        'layout_json' => 'array',
    ];

    public function getTable(): string
    {
        return config('visual-dashboards.tables.dashboards', 'custom_dashboards');
    }

    protected static function newFactory(): CustomDashboardFactory
    {
        return CustomDashboardFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $dashboard): void {
            if (blank($dashboard->slug) && filled($dashboard->name)) {
                $dashboard->slug = static::uniqueSlugForUser((int) $dashboard->user_id, (string) $dashboard->name);
            }

            if (blank($dashboard->heading) && filled($dashboard->name)) {
                $dashboard->heading = (string) $dashboard->name;
            }

            if ($dashboard->is_default) {
                static::query()
                    ->where('user_id', $dashboard->user_id)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function (self $dashboard): void {
            if ($dashboard->isDirty('name') && blank($dashboard->slug)) {
                $dashboard->slug = static::uniqueSlugForUser((int) $dashboard->user_id, (string) $dashboard->name, $dashboard->id);
            }

            if ($dashboard->isDirty('is_default') && $dashboard->is_default) {
                static::query()
                    ->where('user_id', $dashboard->user_id)
                    ->whereKeyNot($dashboard->id)
                    ->update(['is_default' => false]);
            }
        });

        static::saving(function (self $dashboard): void {
            $roles = is_array($dashboard->viewer_roles) ? $dashboard->viewer_roles : [];
            $dashboard->viewer_roles = array_values(array_filter(array_map(
                static fn (mixed $role): string => trim((string) $role),
                $roles
            )));
        });
    }

    public function displayHeading(): string
    {
        return (string) ($this->heading ?: $this->name);
    }

    /**
     * @return list<string>
     */
    public function viewerRoles(): array
    {
        if (! is_array($this->viewer_roles)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $role): string => trim((string) $role),
            $this->viewer_roles
        )));
    }

    public function isPubliclyViewable(): bool
    {
        return DashboardAccess::isPubliclyViewable($this);
    }

    public function allowsAuthenticatedViewer(?Authenticatable $user): bool
    {
        return DashboardAccess::canViewInApp($user, $this);
    }

    public static function uniqueSlugForUser(int $userId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'dashboard';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('user_id', $userId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        $model = config('visual-dashboards.user_model', 'App\\Models\\User');

        return $this->belongsTo($model);
    }

    public function dashlets(): HasMany
    {
        return $this->hasMany(Dashlet::class, 'custom_dashboard_id');
    }

    /**
     * Share URL for this dashboard (works for signed-in allowed viewers always;
     * anonymous access also requires Public link).
     */
    public function shareUrl(): ?string
    {
        if (blank($this->slug) || ! $this->exists) {
            return null;
        }

        $path = trim((string) config('visual-dashboards.public.path', 'visual-dashboards'), '/');

        return url($path.'/'.$this->getKey().'/'.$this->slug);
    }

    /**
     * @deprecated Prefer shareUrl(); kept as an alias for callers/exports.
     */
    public function publicUrl(): ?string
    {
        return $this->shareUrl();
    }

    public static function findPublished(int $id, ?string $slug = null): ?self
    {
        $dashboard = static::query()
            ->whereKey($id)
            ->where('is_published', true)
            ->first();

        if (! $dashboard || ! $dashboard->isPubliclyViewable()) {
            return null;
        }

        if (filled($slug) && $dashboard->slug !== $slug) {
            return null;
        }

        return $dashboard;
    }
}
