<?php
namespace App\Http\Controllers\Partner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
/**
 * وحدات بوابة الشريك غير المبنيّة بعد (بنية فقط، بلا بيانات وهمية).
 * سطح المنتَج (اللوحة والطلبات) انتقل إلى React — انظر Inertia\Partner.
 */
class PartnerPortalController extends Controller {
    private function agency(Request $r) { return $r->attributes->get('activeAgency'); }
    public function stub(Request $r, string $section) { return view('partner.not-available', ['section' => $section]); }
}
