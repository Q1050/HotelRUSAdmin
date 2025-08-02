
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ReactNode } from "react";

interface ActionButtonProps {
  icon: ReactNode;
  children: ReactNode;
  variant?: "default" | "outline" | "ghost";
  className?: string;
  onClick?: () => void;
}

export const ActionButton = ({
  icon,
  children,
  variant = "default",
  className,
  onClick,
}: ActionButtonProps) => {
  return (
    <Button
      variant={variant}
      onClick={onClick}
      className={cn("flex items-center gap-2", className)}
    >
      {icon}
      <span>{children}</span>
    </Button>
  );
};
