
import { ReactNode } from "react";
import { cn } from "@/lib/utils";

interface DashboardCardProps {
  title: string;
  value: string | number;
  icon: ReactNode;
  className?: string;
  trend?: {
    value: number;
    isPositive: boolean;
  };
}

export const DashboardCard = ({
  title,
  value,
  icon,
  className,
  trend,
}: DashboardCardProps) => {
  return (
    <div
      className={cn(
        "bg-white p-6 rounded-lg shadow-md border border-hotel-beige/50 hover:shadow-lg transition-all",
        className
      )}
    >
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium text-gray-500">{title}</h3>
          <div className="mt-1 flex items-baseline">
            <p className="text-2xl font-semibold text-hotel-navy">{value}</p>
            {trend && (
              <span
                className={cn(
                  "ml-2 text-xs",
                  trend.isPositive ? "text-green-600" : "text-red-600"
                )}
              >
                {trend.isPositive ? "+" : "-"}
                {trend.value}%
              </span>
            )}
          </div>
        </div>
        <div className="p-2 rounded-full bg-hotel-beige text-hotel-navy">
          {icon}
        </div>
      </div>
    </div>
  );
};
