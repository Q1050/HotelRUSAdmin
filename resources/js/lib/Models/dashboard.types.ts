export interface DashboardModel {
  user: UserModel;
  stats: { checkinsToday: number; pendingVerifications: number; availableRooms: number; arrivalsToday: number; departuresToday: number; roomsCleaning: number; offlineLocks: number; housekeepingPending: number; maintenanceOpen: number };
  recentCheckins: Array<{
    id: number; name: string; email: string | null; checkInTime: string;
    idStatus: "verified" | "pending" | "rejected"; roomNumber: string | null;
  }>;
}
