<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
/**
 * ما تبقّى من بوابة العميل على Blade: الوحدات غير المبنيّة فقط.
 * سطح المنتَج كلّه انتقل إلى React — انظر Inertia\Client.
 */
class ClientPortalController extends Controller {
    public function stub(Request $r, string $section) { return view('client.not-available', ['section' => $section]); }
}
