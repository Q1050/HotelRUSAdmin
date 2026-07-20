<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'email' => $this->email,
                'formality' => $this->formality,
                'initials' => strtoupper(substr($this->firstName, 0, 1) . substr($this->lastName, 0, 1)),
                'role' => $this->role,
                'status' => $this->status,
            ],
            // Add more dashboard data here later (e.g. stats, check-ins)
        ];
    }
}
