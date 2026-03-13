<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Central\Domain;
use App\Models\Central\School;
use Illuminate\Support\Facades\Cache;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Identification Variables
        $host = $request->getHost();
        $port = $request->getPort();
        $fullHost = $port && !in_array($port, [80, 443]) ? "{$host}:{$port}" : $host;
        $pathSegment = $request->segment(1);

        // Dynamically add current host to Sanctum stateful domains
        $stateful = config('sanctum.stateful', []);
        if (!in_array($fullHost, $stateful)) {
            $stateful[] = $fullHost;
            config(['sanctum.stateful' => $stateful]);
        }
        
        // Update APP_URL dynamically for correct link generation
        $scheme = $request->isSecure() ? 'https' : 'http';
        config(['app.url' => "{$scheme}://{$fullHost}"]);
        
        $tenantIdentifier = null;
        $isPathBased = false;

        // 2. Try to find domain by host first
        $domain = $this->resolveDomain($host);

        if ($domain) {
            $tenantIdentifier = $domain->school_id;
        } elseif ($pathSegment) {
            // 3. Fallback: Check if the first path segment matches a domain record 
            $domainByPath = $this->resolveDomain($pathSegment);
            if ($domainByPath) {
                $tenantIdentifier = $domainByPath->school_id;
                $isPathBased = true;
            }
        }

        // 4. Handle Unidentified Tenants — Central Domain
        if (! $tenantIdentifier) {
            $centralDomain = env('CENTRAL_DOMAIN', 'governance.localhost');
            
            if ($host === $centralDomain || $host === 'localhost' || $host === '127.0.0.1') {
                $centralSessionPath = storage_path('framework/sessions/central');
                if (!is_dir($centralSessionPath)) {
                    mkdir($centralSessionPath, 0755, true);
                }
                config(['session.files' => $centralSessionPath]);
                app()->instance('tenant.central', true);

                return $next($request);
            }
            
            abort(404, 'Tenant not found. The domain or school code is invalid.');
        }

        // 5. Load the School Data (Cached)
        $school = Cache::remember("tenant_school_{$tenantIdentifier}", 3600, function () use ($tenantIdentifier) {
            return School::on('central')
                ->where('status', 'active')
                ->find($tenantIdentifier);
        });

        if (! $school) {
            abort(403, 'Tenant is suspended or does not exist.');
        }

        // ─── 6. SET SASS SCHOOL ID ──────────────────────────────────
        // This is the KEY line: it tells the SassSchoolScope which 
        // school's data to show. All queries auto-filter by this.
        app()->instance('sass_school_id', $school->id);
        // ─────────────────────────────────────────────────────────────

        // 7. Set default connection to tenant (shared DB, no switching needed)
        \Illuminate\Support\Facades\DB::setDefaultConnection('tenant');

        // 8. Session Isolation per school
        $sessionPath = storage_path('framework/sessions/tenants/' . $school->id);
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0755, true);
        }
        config(['session.files' => $sessionPath]);

        // 9. Storage Isolation per school
        config(['filesystems.disks.local.root' => storage_path('app/tenants/' . $school->id)]);
        config(['filesystems.disks.public.root' => storage_path('app/public/tenants/' . $school->id)]);
        config(['filesystems.disks.public.url' => env('APP_URL') . '/storage/tenants/' . $school->id]);

        // 10. Cache Isolation per school
        config(['cache.prefix' => 'tenant_' . $school->id . '_cache_']);
        if (config('database.redis.default')) {
            config(['database.redis.options.prefix' => 'tenant_' . $school->id . '_redis_']);
        }

        // 11. Bind school to Container
        app()->instance('tenant.active', $school);
        config(['tenant.is_path_based' => $isPathBased]);
        config(['tenant.path_segment' => $isPathBased ? $pathSegment : null]);

        // 12. Transparent Path Shifting (for localhost/school1 development pattern)
        if ($isPathBased) {
            $newUri = preg_replace('/^\/' . preg_quote($pathSegment, '/') . '/', '', $request->getRequestUri());
            if (empty($newUri)) {
                $newUri = '/';
            }
            $request->server->set('REQUEST_URI', $newUri);
            
            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                $request->getContent()
            );
        }

        return $next($request);
    }

    protected function resolveDomain(string $domainName)
    {
        return Cache::remember("tenant_domain_{$domainName}", 3600, function () use ($domainName) {
            return Domain::on('central')->where('domain', $domainName)->first();
        });
    }
}
