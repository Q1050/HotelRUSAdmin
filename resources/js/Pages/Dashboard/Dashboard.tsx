
import { AdminLayout } from "@/components/layout/AdminLayout";
import { DashboardCard } from "@/components/DashboardCard";
import { DataTable } from "@/components/DataTable";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { ActionButton } from "@/components/ActionButton";
import { Button } from "@/components/ui/button";
import { DashboardModel } from "@/lib/Models/dashboard.types";
import { 
  Users, 
  ClipboardCheck, 
  Bed, 
  Eye, 
  CheckCircle, 
  RefreshCw,
  Plus
} from "lucide-react";
import { AddButton } from "@/components/Sidebar";
import { GuestFormModal } from "@/components/GuestFormModal";
import { GuestCheckinModal } from "@/components/GuestCheckinModal";
import { router } from "@inertiajs/react";
import { useState } from "react";

interface RecentCheckIn {
  id: number;
  name: string;
  email: string | null;
  checkInTime: string;
  idStatus: "verified" | "pending" | "rejected";
  roomNumber: string | null;
}

interface DashboardProps {
  DashboardModel: DashboardModel;
}

const Dashboard = ({ DashboardModel }: DashboardProps) => {
  const [showCreate, setShowCreate] = useState(false);
  const [showCheckin, setShowCheckin] = useState(false);
  const user = DashboardModel?.user;
  const recentCheckIns = DashboardModel?.recentCheckins ?? [];

  if (!user) {
    return <div>Loading...</div>;
  }
  const checkInColumns = [
    {
      header: "Guest Name",
      accessor: "name" as const,
    },
    {
      header: "Check-in Time",
      accessor: "checkInTime" as const,
      cell: (item: RecentCheckIn) => {
        const date = new Date(item.checkInTime);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      },
    },
    {
      header: "ID Status",
      accessor: "idStatus" as const,
      cell: (item: RecentCheckIn) => (
        <StatusBadge 
          status={item.idStatus as "verified" | "pending" | "rejected"} 
        />
      ),
    },
    {
      header: "Room",
      accessor: "roomNumber" as const,
      cell: (item: RecentCheckIn) => (
        item.roomNumber ? item.roomNumber : <span className="text-gray-400">Not Assigned</span>
      ),
    },
    {
      header: "Actions",
      accessor: "id" as const,
      cell: (item: RecentCheckIn) => (
        <div className="flex gap-2">
          <Button 
            variant="outline" 
            size="sm"
            className="flex items-center gap-1"
            onClick={() => window.location.href = route('dashboard.guests.show', item.id)}
          >
            <Eye size={14} />
            <span className="hidden sm:inline">View</span>
          </Button>
          {item.idStatus === "pending" && (
            <Button 
              variant="outline" 
              size="sm"
            className="flex items-center gap-1 text-green-600 border-green-600 hover:bg-green-50"
            onClick={() => router.patch(route('dashboard.guests.verify-id', item.id))}
            >
              <CheckCircle size={14} />
              <span className="hidden sm:inline">Verify</span>
            </Button>
          )}
        </div>
      ),
    }
  ];

  return (
    <AdminLayout title="Dashboard" user={user}>
      <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Welcome Back {user.formality}.{user.lastName}</h1>
          <p className="text-gray-600">Here's what's happening today</p>
        </div>
        <div className="flex gap-3">
          <ActionButton onClick={() => router.reload({ only: ['DashboardModel'] })}
            icon={<RefreshCw size={16} />}
            variant="outline"
          >
            Refresh
          </ActionButton>
          <AddButton onClick={() => setShowCheckin(true)}>
            New Check-in
          </AddButton>
        </div>
      </div>
      <GuestFormModal open={showCreate} onClose={() => setShowCreate(false)} />
      <GuestCheckinModal open={showCheckin} onClose={() => setShowCheckin(false)} onCreateAccount={() => setShowCreate(true)} />

      {/* Dashboard Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <DashboardCard
          title="Total Check-ins Today"
          value={DashboardModel.stats.checkinsToday}
          icon={<Users size={24} />}
          trend={{ value: 10, isPositive: true }}
        />
        <DashboardCard
          title="Pending Verifications"
          value={DashboardModel.stats.pendingVerifications}
          icon={<ClipboardCheck size={24} />}
          trend={{ value: 2, isPositive: false }}
        />
        <DashboardCard
          title="Available Rooms"
          value={DashboardModel.stats.availableRooms}
          icon={<Bed size={24} />}
          trend={{ value: 0, isPositive: true }}
        />
      </div>
      <div className="mb-8 grid grid-cols-2 gap-3 md:grid-cols-5">
        {[['Arrivals',DashboardModel.stats.arrivalsToday],['Departures',DashboardModel.stats.departuresToday],['Cleaning',DashboardModel.stats.roomsCleaning],['Housekeeping',DashboardModel.stats.housekeepingPending],['Maintenance',DashboardModel.stats.maintenanceOpen],['Offline locks',DashboardModel.stats.offlineLocks]].map(([label,value])=><div key={label} className="rounded-lg bg-white p-3 shadow"><p className="text-xs text-gray-500">{label}</p><p className="text-xl font-semibold">{value}</p></div>)}
      </div>

      {/* Recent Check-ins */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="p-4 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-800">Recent Check-ins</h2>
        </div>
        <DataTable
          data={recentCheckIns}
          columns={checkInColumns}
          onRowClick={(item) => router.visit(route('dashboard.guests.show', item.id))}
        />
      </div>
    </AdminLayout>
  );
};

export default Dashboard;
