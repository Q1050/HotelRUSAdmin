
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

// Mock data for recent check-ins
const recentCheckIns = [
  { 
    id: 1, 
    name: "John Smith", 
    email: "john.smith@example.com",
    checkInTime: "2025-04-23T09:30:00", 
    idStatus: "verified", 
    roomNumber: "101" 
  },
  { 
    id: 2, 
    name: "Sarah Johnson", 
    email: "sarah.j@example.com", 
    checkInTime: "2025-04-23T10:15:00", 
    idStatus: "pending", 
    roomNumber: "205" 
  },
  { 
    id: 3, 
    name: "Michael Brown", 
    email: "m.brown@example.com", 
    checkInTime: "2025-04-23T11:00:00", 
    idStatus: "verified", 
    roomNumber: "310" 
  },
  { 
    id: 4, 
    name: "Emily Davis", 
    email: "emily.d@example.com", 
    checkInTime: "2025-04-23T12:30:00", 
    idStatus: "pending", 
    roomNumber: "" 
  },
];
interface DashboardProps {
  DashboardModel: DashboardModel;
}

const Dashboard = ({ DashboardModel }: DashboardProps) => {
  console.log("Dashboard Model:", DashboardModel);
  const user = DashboardModel?.user;

  if (!user) {
    return <div>Loading...</div>;
  }
  console.log("Dashboard user:", user);
  const checkInColumns = [
    {
      header: "Guest Name",
      accessor: "name" as const,
    },
    {
      header: "Check-in Time",
      accessor: "checkInTime" as const,
      cell: (item: typeof recentCheckIns[0]) => {
        const date = new Date(item.checkInTime);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      },
    },
    {
      header: "ID Status",
      accessor: "idStatus" as const,
      cell: (item: typeof recentCheckIns[0]) => (
        <StatusBadge 
          status={item.idStatus as "verified" | "pending" | "rejected"} 
        />
      ),
    },
    {
      header: "Room",
      accessor: "roomNumber" as const,
      cell: (item: typeof recentCheckIns[0]) => (
        item.roomNumber ? item.roomNumber : <span className="text-gray-400">Not Assigned</span>
      ),
    },
    {
      header: "Actions",
      accessor: "id" as const,
      cell: (item: typeof recentCheckIns[0]) => (
        <div className="flex gap-2">
          <Button 
            variant="outline" 
            size="sm"
            className="flex items-center gap-1"
          >
            <Eye size={14} />
            <span className="hidden sm:inline">View</span>
          </Button>
          {item.idStatus === "pending" && (
            <Button 
              variant="outline" 
              size="sm"
              className="flex items-center gap-1 text-green-600 border-green-600 hover:bg-green-50"
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
      <div className="w-full mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Welcome {user.formality} {user.lastName}</h1>
          <p className="text-gray-600">Here's what's happening today</p>
        </div>
        <div className="flex gap-3">
          <ActionButton
            icon={<RefreshCw size={16} />}
            variant="outline"
          >
            Refresh
          </ActionButton>
          <AddButton>
            New Check-in
          </AddButton>
        </div>
      </div>

      {/* Dashboard Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <DashboardCard
          title="Total Check-ins Today"
          value={12}
          icon={<Users size={24} />}
          trend={{ value: 10, isPositive: true }}
        />
        <DashboardCard
          title="Pending Verifications"
          value={5}
          icon={<ClipboardCheck size={24} />}
          trend={{ value: 2, isPositive: false }}
        />
        <DashboardCard
          title="Available Rooms"
          value={24}
          icon={<Bed size={24} />}
          trend={{ value: 0, isPositive: true }}
        />
      </div>

      {/* Recent Check-ins */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="p-4 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-800">Recent Check-ins</h2>
        </div>
        <DataTable
          data={recentCheckIns}
          columns={checkInColumns}
          onRowClick={(item) => console.log("Clicked row:", item)}
        />
      </div>
    </AdminLayout>
  );
};

export default Dashboard;
