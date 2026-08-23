<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ترجمة أزرار الترقيم — كانت غائبة فتظهر «pagination.previous/next» كمفاتيح خام
 * في كل قائمة مُرقّمة على الإنتاج (الحملات/العملاء/المحتوى...). هذه تمنع عودتها.
 */
class PaginationLangTest extends TestCase
{
    public function test_pagination_labels_are_translated_not_raw_keys(): void
    {
        app()->setLocale('ar');
        $this->assertNotSame('pagination.previous', __('pagination.previous'));
        $this->assertNotSame('pagination.next', __('pagination.next'));
        $this->assertStringContainsString('السابق', __('pagination.previous'));

        app()->setLocale('en');
        $this->assertNotSame('pagination.previous', __('pagination.previous'));
        $this->assertStringContainsString('Previous', __('pagination.previous'));
    }
}
