<?php

namespace App\Http\Resources\Helpdesk\Faq;

use App\Enums\Helpdesk\Faq\Status;
use App\Http\Resources\OptionResource;
use App\Http\Resources\TagResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class FaqResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'question' => $this->question,
            'question_excerpt' => Str::summary($this->question),
            'category' => OptionResource::make($this->whenLoaded('category')),
            'answer' => $this->answer,
            'status' => Status::getDetail($this->status),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tag_summary' => $this->showTags(),
            'is_published' => $this->status == Status::PUBLISHED ? true : false,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
