<?php
namespace App\Domain\CRM\Enums;
enum ClientType: string {
    case Company='company'; case BrandOwner='brand_owner'; case Government='government';
    case Nonprofit='nonprofit'; case Agency='agency'; case Individual='individual'; case Other='other';
}
