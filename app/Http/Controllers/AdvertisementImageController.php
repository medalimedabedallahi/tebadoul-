<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves an advertisement's banner from the database.
 *
 * Anyone may load the banner of a displayable advertisement; the URL carries the image checksum,
 * so the response is cached for good. Administrators may also preview one that is not shown, which
 * is never cached. Anything else is not found.
 */
class AdvertisementImageController extends Controller
{
    public function __invoke(Request $request, Advertisement $advertisement): Response
    {
        $displayable = $advertisement->isDisplayable();

        abort_unless($displayable || $request->user()?->can('view', $advertisement), 404);

        return response($advertisement->imageBytes(), 200, [
            'Content-Type' => $advertisement->image_mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $displayable ? 'public, max-age=31536000, immutable' : 'private, no-store',
        ]);
    }
}
