<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $users = $request->users ?? collect([]);
        $userRoles = $request->user_roles ?? collect([]);

        $teamUsers = $users->where('team_id', $this->id);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            $this->mergeWhen($request->has_users, [
                'users' => $teamUsers ? UserSummaryResource::collection($teamUsers) : [],
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'alias' => $this->alias,
            'config' => $this->config,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
