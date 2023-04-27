<?php

namespace App\DTO;

use App\Http\Requests\PostStoreRequest;
use App\Models\Post;
use Illuminate\Http\Request;

class PostDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $content
    ) {
    }

    public static function create(PostStoreRequest $request): PostDTO
    {
        /**
         * 4.使用DTO建立資料
         * 請求的資料可以在DTO層變換
         * ex: title: $request->name,
         * ex: age: $nowYear - $request->year,
         */
        return new self(
            title: $request->title,
            content: $request->content,
        );
    }
}