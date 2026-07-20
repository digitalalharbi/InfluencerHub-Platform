<?php
namespace App\Http\Controllers\Creator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * ما تبقّى من بوابة المبدع على Blade: الوحدات غير المبنيّة فقط.
 * سطح المنتَج (اللوحة والحساب والتعاونات والمحتوى والعقود والمستحقات)
 * انتقل إلى React — انظر Inertia\Creator.
 */
class CreatorPortalController extends Controller {
    public function stub(Request $r, string $section) {
        // وحدات لم تبدأ مراحلها بعد — لا بيانات وهمية
        return view('creator.not-available', ['section' => $section]);
    }
}