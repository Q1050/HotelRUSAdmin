import { FormEvent, useState } from "react";
import { Link, router } from "@inertiajs/react";
import { ArrowLeft, Search } from "lucide-react";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

type Event = {
    id: number;
    type: string;
    description: string;
    guestName: string | null;
    actorName: string | null;
    occurredAt: string | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type EventPage = {
    data: Event[];
    links: PageLink[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
type Filters = { search: string; type: string; from: string; to: string };

export default function History({
    room,
    events,
    eventTypes,
    filters,
}: {
    room: { id: number; number: string; type: string; floor: number };
    events: EventPage;
    eventTypes: string[];
    filters: Filters;
}) {
    const [values, setValues] = useState(filters);
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(route("dashboard.rooms.history", room.id), values, {
            preserveState: true,
            replace: true,
        });
    };
    const clear = () => {
        const empty = { search: "", type: "", from: "", to: "" };
        setValues(empty);
        router.get(route("dashboard.rooms.history", room.id), empty, {
            replace: true,
        });
    };

    return (
        <AdminLayout title={`Room ${room.number} History`}>
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link
                        href={route("dashboard.rooms")}
                        className="mb-2 inline-flex items-center gap-2 text-sm font-medium text-hotel-navy hover:underline"
                    >
                        <ArrowLeft size={16} />
                        Back to rooms
                    </Link>
                    <h1 className="text-2xl font-bold">
                        Room {room.number} history
                    </h1>
                    <p className="text-gray-600">
                        {room.type} · Floor {room.floor} · {events.total}{" "}
                        recorded events
                    </p>
                </div>
            </div>

            <form
                onSubmit={apply}
                className="mb-6 rounded-lg bg-white p-6 shadow"
            >
                <div className="grid gap-6 md:grid-cols-2">
                    <label className="block min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm font-medium text-gray-700">
                        <span>Search history</span>
                        <div className="relative mt-2">
                            <Search
                                className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                                size={18}
                            />
                            <Input
                                className="h-10 w-full pl-10"
                                value={values.search}
                                onChange={(e) =>
                                    setValues({
                                        ...values,
                                        search: e.target.value,
                                    })
                                }
                                placeholder="Description, guest, or staff…"
                            />
                        </div>
                    </label>
                    <label className="block min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm font-medium text-gray-700">
                        <span>Event type</span>
                        <select
                            className="mt-2 w-full rounded-md border border-gray-300 px-3 py-2"
                            value={values.type}
                            onChange={(e) =>
                                setValues({ ...values, type: e.target.value })
                            }
                        >
                            <option value="">All event types</option>
                            {eventTypes.map((type) => (
                                <option key={type} value={type}>
                                    {formatType(type)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm font-medium text-gray-700">
                        <span>From date</span>
                        <Input
                            className="mt-2 w-full"
                            type="date"
                            value={values.from}
                            onChange={(e) =>
                                setValues({ ...values, from: e.target.value })
                            }
                        />
                    </label>
                    <label className="block min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm font-medium text-gray-700">
                        <span>To date</span>
                        <Input
                            className="mt-2 w-full"
                            type="date"
                            value={values.to}
                            onChange={(e) =>
                                setValues({ ...values, to: e.target.value })
                            }
                        />
                    </label>
                </div>
                <div className="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                    <Button type="button" variant="outline" onClick={clear}>
                        Clear filters
                    </Button>
                    <Button type="submit">Apply filters</Button>
                </div>
            </form>

            <div className="overflow-hidden rounded-lg bg-white shadow">
                <div className="space-y-3 p-5">
                    {events.data.map((event) => (
                        <article
                            key={event.id}
                            className={`rounded-lg border-l-4 p-4 ${eventCardStyle(event.type)}`}
                        >
                            <div className="flex flex-col justify-between gap-2 sm:flex-row">
                                <div>
                                    <span
                                        className={`mb-2 inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${eventBadgeStyle(event.type)}`}
                                    >
                                        {formatType(event.type)}
                                    </span>
                                    <p className="text-sm font-medium text-gray-900">
                                        {event.description}
                                    </p>
                                    <p className="mt-1 text-xs text-gray-500">
                                        {event.guestName &&
                                            `Guest: ${event.guestName}`}
                                        {event.guestName &&
                                            event.actorName &&
                                            " · "}
                                        {event.actorName &&
                                            `Staff: ${event.actorName}`}
                                        {!event.guestName &&
                                            !event.actorName &&
                                            "System event"}
                                    </p>
                                </div>
                                <time className="whitespace-nowrap text-xs text-gray-500">
                                    {event.occurredAt
                                        ? new Date(
                                              event.occurredAt,
                                          ).toLocaleString()
                                        : "Date unavailable"}
                                </time>
                            </div>
                        </article>
                    ))}
                    {events.data.length === 0 && (
                        <p className="p-10 text-center text-gray-500">
                            No room history matches these filters.
                        </p>
                    )}
                </div>
                {events.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4">
                        <p className="text-sm text-gray-500">
                            Showing {events.from}–{events.to} of {events.total}
                        </p>
                        <nav className="flex flex-wrap gap-1">
                            {events.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? "bg-hotel-navy text-white" : "bg-white text-gray-700 hover:bg-gray-50"}`}
                                    >
                                        {paginationLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={index}
                                        className="cursor-not-allowed rounded-md border px-3 py-1.5 text-sm text-gray-300"
                                    >
                                        {paginationLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </nav>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

const formatType = (type: string) =>
    type
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const paginationLabel = (label: string) =>
    label
        .replace("&laquo; Previous", "Previous")
        .replace("Next &raquo;", "Next");
const eventCategory = (type: string) =>
    type.includes("maintenance")
        ? "maintenance"
        : type.includes("housekeeping") || type.includes("cleaning")
          ? "housekeeping"
          : type.includes("lock") ||
              type.includes("key") ||
              type.includes("access") ||
              type.includes("credential") ||
              type.includes("unlock") ||
              type.includes("revoked")
            ? "access"
            : type.includes("guest") ||
                type.includes("check") ||
                type.includes("assigned") ||
                type.includes("released")
              ? "guest"
              : type.includes("room")
                ? "room"
                : "other";
const eventBadgeStyle = (type: string) =>
    ({
        maintenance: "border border-red-300 bg-red-100 text-red-800",
        housekeeping: "border border-amber-300 bg-amber-100 text-amber-800",
        access: "border border-purple-300 bg-purple-100 text-purple-800",
        guest: "border border-green-300 bg-green-100 text-green-800",
        room: "border border-blue-300 bg-blue-100 text-blue-800",
        other: "border border-cyan-300 bg-cyan-100 text-cyan-800",
    })[eventCategory(type)];
const eventCardStyle = (type: string) =>
    ({
        maintenance: "border-red-400 bg-red-50",
        housekeeping: "border-amber-400 bg-amber-50",
        access: "border-purple-400 bg-purple-50",
        guest: "border-green-400 bg-green-50",
        room: "border-blue-400 bg-blue-50",
        other: "border-cyan-400 bg-cyan-50",
    })[eventCategory(type)];
