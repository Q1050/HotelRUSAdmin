import { FormEvent, useState } from "react";
import { Link, router, useForm } from "@inertiajs/react";
import { Activity, Download, RotateCcw, Search, ShieldAlert, Trash2 } from "lucide-react";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
type Event = {
    id: number;
    action: string;
    category: string;
    severity: "normal" | "sensitive" | "warning" | "critical";
    description: string;
    reason: string | null;
    actor: string | null;
    ipAddress: string | null;
    occurredAt: string | null;
    metadata: unknown;
};
type Page = {
    data: Event[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
    from: number | null;
    to: number | null;
    last_page: number;
};
type Filters = {
    search: string;
    category: string;
    severity: string;
    from: string;
    to: string;
};
type FailedJob = {
    uuid: string;
    name: string;
    queue: string;
    connection: string;
    error: string;
    failedAt: string;
};
const severityStyle = {
    normal: "bg-green-100 text-green-800",
    sensitive: "bg-blue-100 text-blue-800",
    warning: "bg-amber-100 text-amber-800",
    critical: "bg-red-100 text-red-800",
};
export default function Security({
    events,
    categories,
    filters,
    stats,
    retentionDays,
    twoFactorEnabled,
    failedJobs,
    queueStats,
    operationsSummary,
    operationsSettings,
    backups,
}: {
    events: Page;
    categories: string[];
    filters: Filters;
    stats: {
        critical: number;
        warnings: number;
        failedLogins: number;
        remoteUnlocks: number;
    };
    retentionDays: number;
    twoFactorEnabled: boolean;
    failedJobs: FailedJob[];
    queueStats: { pending: number; failed: number; oldestMinutes: number; schedulerLastRun: string | null };
    operationsSummary: { offlineLocks: number; lowBatteryLocks: number; fcmFailures: number; status: "healthy" | "attention" };
    operationsSettings: { alerts_enabled: boolean; email_enabled: boolean; alert_roles: string[]; alert_emails: string; low_battery_threshold: number; lock_offline_minutes: number; fcm_failure_threshold: number; alert_cooldown_minutes: number };
    backups: { id:number; status:string; sizeBytes:number; records:number; files:number; missingFiles:number; createdAt:string|null; verifiedAt:string|null; error:string|null }[];
}) {
    const [values, setValues] = useState(filters);
    const settings = useForm({
        retention_days: String(retentionDays),
        two_factor_enabled: twoFactorEnabled,
        alerts_enabled: operationsSettings.alerts_enabled,
        email_enabled: operationsSettings.email_enabled,
        alert_roles: operationsSettings.alert_roles,
        alert_emails: operationsSettings.alert_emails,
        low_battery_threshold: String(operationsSettings.low_battery_threshold),
        lock_offline_minutes: String(operationsSettings.lock_offline_minutes),
        fcm_failure_threshold: String(operationsSettings.fcm_failure_threshold),
        alert_cooldown_minutes: String(operationsSettings.alert_cooldown_minutes),
    });
    const apply = (e: FormEvent) => {
        e.preventDefault();
        router.get(route("dashboard.security.index"), values, {
            preserveState: true,
            replace: true,
        });
    };
    const query = new URLSearchParams(
        Object.entries(values).filter(([, v]) => v),
    ).toString();
    return (
        <AdminLayout title="Security & Audit">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">
                        Security and audit center
                    </h1>
                    <p className="text-gray-600">
                        Review sensitive activity, access events, and
                        authentication warnings.
                    </p>
                </div>
                <a href={`${route("dashboard.security.export")}?${query}`}>
                    <Button variant="outline">
                        <Download className="mr-2" size={16} />
                        Export CSV
                    </Button>
                </a>
            </div>
            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    ["Critical · 7 days", stats.critical, "text-red-600"],
                    ["Warnings · 7 days", stats.warnings, "text-amber-600"],
                    ["Failed logins · 24h", stats.failedLogins, "text-red-600"],
                    [
                        "Remote unlocks · 24h",
                        stats.remoteUnlocks,
                        "text-purple-600",
                    ],
                ].map(([label, value, color]) => (
                    <div
                        key={String(label)}
                        className=" rounded-lg bg-white px-4 py-5 shadow-sm"
                    >
                        <p className="text-sm text-gray-500">{label}</p>
                        <p className={`mt-1 text-2xl font-semibold ${color}`}>
                            {value}
                        </p>
                    </div>
                ))}
            </div>
            <section className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <span className="rounded-lg bg-indigo-50 p-2 text-indigo-700">
                            <Activity size={20} />
                        </span>
                        <div>
                            <h2 className="text-lg font-semibold">Background operations</h2>
                            <p className="text-sm text-gray-500">
                                {queueStats.pending} pending · {queueStats.failed} failed. Notifications and lock checks retry automatically.
                            </p>
                            <p className="mt-1 text-xs text-gray-400">
                                Oldest queued job: {queueStats.oldestMinutes} min · Scheduler: {queueStats.schedulerLastRun ? new Date(queueStats.schedulerLastRun).toLocaleString() : "No heartbeat yet"}
                            </p>
                        </div>
                    </div>
                    {failedJobs.length > 0 && (
                        <Button
                            variant="outline"
                            onClick={() => router.post(route("dashboard.security.failed-jobs.retry-all"), {}, { preserveScroll: true })}
                        >
                            <RotateCcw className="mr-2" size={15} /> Retry all
                        </Button>
                    )}
                </div>
                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                    {[
                        ["Offline or stale locks", operationsSummary.offlineLocks],
                        ["Low-battery locks", operationsSummary.lowBatteryLocks],
                        ["FCM failures · 1h", operationsSummary.fcmFailures],
                    ].map(([label, count]) => (
                        <div key={String(label)} className={`rounded-lg border p-4 ${Number(count) ? "border-amber-200 bg-amber-50" : "border-green-200 bg-green-50"}`}>
                            <p className="text-xs font-medium text-gray-600">{label}</p>
                            <p className={`mt-1 text-xl font-semibold ${Number(count) ? "text-amber-700" : "text-green-700"}`}>{count}</p>
                        </div>
                    ))}
                </div>
                <div className="mt-5 space-y-3">
                    {failedJobs.map((job) => (
                        <article key={job.uuid} className="rounded-lg border bg-gray-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <strong className="text-sm">{job.name}</strong>
                                        <span className="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-700">failed</span>
                                        <span className="rounded-full bg-white px-2.5 py-1 text-[11px] text-gray-600">{job.queue}</span>
                                    </div>
                                    <p className="mt-2 break-words text-sm text-gray-600">{job.error || "No error summary available."}</p>
                                    <p className="mt-2 text-xs text-gray-500">{job.connection} · {new Date(job.failedAt).toLocaleString()}</p>
                                </div>
                                <div className="flex gap-2">
                                    <Button size="sm" variant="outline" onClick={() => router.post(route("dashboard.security.failed-jobs.retry", job.uuid), {}, { preserveScroll: true })}>
                                        <RotateCcw className="mr-1.5" size={14} /> Retry
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={() => {
                                        if (confirm("Delete this failed-job record?")) router.delete(route("dashboard.security.failed-jobs.delete", job.uuid), { preserveScroll: true });
                                    }}>
                                        <Trash2 className="mr-1.5" size={14} /> Delete
                                    </Button>
                                </div>
                            </div>
                        </article>
                    ))}
                    {failedJobs.length === 0 && (
                        <div className="rounded-lg border border-dashed bg-green-50 p-5 text-sm text-green-800">No failed background jobs need attention.</div>
                    )}
                </div>
            </section>
            <section className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4"><div><h2 className="text-lg font-semibold">Encrypted property backups</h2><p className="text-sm text-gray-500">Database records and referenced private files, protected with the application encryption key.</p></div><Button onClick={() => router.post(route("dashboard.security.backups.create"), {}, { preserveScroll:true })}>Create backup</Button></div>
                <div className="mt-5 space-y-3">{backups.map(backup=><article key={backup.id} className="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-gray-50 p-4"><div><div className="flex items-center gap-2"><strong className="text-sm">{backup.createdAt?new Date(backup.createdAt).toLocaleString():"Pending backup"}</strong><span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${backup.status==="verified"?"bg-green-100 text-green-700":backup.status==="failed"||backup.status==="corrupt"?"bg-red-100 text-red-700":"bg-amber-100 text-amber-700"}`}>{backup.status}</span></div><p className="mt-2 text-xs text-gray-500">{(backup.sizeBytes/1048576).toFixed(2)} MB · {backup.records} records · {backup.files} private files{backup.missingFiles?` · ${backup.missingFiles} missing`:""}</p>{backup.error&&<p className="mt-1 text-xs text-red-600">{backup.error}</p>}</div>{backup.status!=="running"&&backup.status!=="pending"&&<Button size="sm" variant="outline" onClick={()=>router.post(route("dashboard.security.backups.verify",backup.id),{},{preserveScroll:true})}>Verify restore readiness</Button>}</article>)}{backups.length===0&&<div className="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">No property backups have been created yet.</div>}</div>
            </section>
            <form
                onSubmit={apply}
                className="mb-6 rounded-xl bg-white p-6 shadow-sm"
            >
                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
                    <label className="text-sm font-medium">
                        Search
                        <div className="relative mt-2">
                            <Search
                                className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                                size={17}
                            />
                            <Input
                                className="pl-10"
                                value={values.search}
                                onChange={(e) =>
                                    setValues({
                                        ...values,
                                        search: e.target.value,
                                    })
                                }
                                placeholder="Action, staff, reason…"
                            />
                        </div>
                    </label>
                    <label className="text-sm font-medium">
                        Category
                        <select
                            className="mt-2 w-full rounded-md border p-2"
                            value={values.category}
                            onChange={(e) =>
                                setValues({
                                    ...values,
                                    category: e.target.value,
                                })
                            }
                        >
                            <option value="">All categories</option>
                            {categories.map((v) => (
                                <option key={v}>{v}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Severity
                        <select
                            className="mt-2 w-full rounded-md border p-2"
                            value={values.severity}
                            onChange={(e) =>
                                setValues({
                                    ...values,
                                    severity: e.target.value,
                                })
                            }
                        >
                            <option value="">All severities</option>
                            {["normal", "sensitive", "warning", "critical"].map(
                                (v) => (
                                    <option key={v}>{v}</option>
                                ),
                            )}
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        From
                        <Input
                            className="mt-2"
                            type="date"
                            value={values.from}
                            onChange={(e) =>
                                setValues({ ...values, from: e.target.value })
                            }
                        />
                    </label>
                    <label className="text-sm font-medium">
                        To
                        <Input
                            className="mt-2"
                            type="date"
                            value={values.to}
                            onChange={(e) =>
                                setValues({ ...values, to: e.target.value })
                            }
                        />
                    </label>
                </div>
                <div className="mt-5 flex justify-end gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            const empty = {
                                search: "",
                                category: "",
                                severity: "",
                                from: "",
                                to: "",
                            };
                            setValues(empty);
                            router.get(
                                route("dashboard.security.index"),
                                empty,
                            );
                        }}
                    >
                        Clear
                    </Button>
                    <Button>Apply filters</Button>
                </div>
            </form>
            <div className="rounded-xl bg-white p-5 shadow-sm">
                <div className="space-y-3">
                    {events.data.map((event) => (
                        <article
                            key={event.id}
                            className="rounded-lg border bg-gray-50 p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${severityStyle[event.severity]}`}
                                        >
                                            {event.severity}
                                        </span>
                                        <span className="rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-gray-600">
                                            {event.category}
                                        </span>
                                        <strong className="text-sm">
                                            {event.action.replaceAll("_", " ")}
                                        </strong>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-800">
                                        {event.description}
                                    </p>
                                    {event.reason && (
                                        <p className="mt-1 text-sm text-gray-600">
                                            <strong>Reason:</strong>{" "}
                                            {event.reason}
                                        </p>
                                    )}
                                    <p className="mt-2 text-xs text-gray-500">
                                        {event.actor ?? "Unknown/system"} ·{" "}
                                        {event.ipAddress ?? "No IP"}
                                    </p>
                                </div>
                                <time className="text-xs text-gray-500">
                                    {event.occurredAt
                                        ? new Date(
                                              event.occurredAt,
                                          ).toLocaleString()
                                        : "—"}
                                </time>
                            </div>
                        </article>
                    ))}
                    {events.data.length === 0 && (
                        <p className="p-10 text-center text-gray-500">
                            No security events match these filters.
                        </p>
                    )}
                </div>
                {events.last_page > 1 && (
                    <nav className="mt-5 flex flex-wrap gap-1 border-t pt-4">
                        {events.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded border px-3 py-1.5 text-sm ${link.active ? "bg-hotel-navy text-white" : "bg-white"}`}
                                >
                                    {link.label
                                        .replace("&laquo; Previous", "Previous")
                                        .replace("Next &raquo;", "Next")}
                                </Link>
                            ) : (
                                <span
                                    key={i}
                                    className="rounded border px-3 py-1.5 text-sm text-gray-300"
                                >
                                    {link.label
                                        .replace("&laquo; Previous", "Previous")
                                        .replace("Next &raquo;", "Next")}
                                </span>
                            ),
                        )}
                    </nav>
                )}
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    settings.patch(route("dashboard.security.settings"), {
                        preserveScroll: true,
                    });
                }}
                className="mt-6 rounded-xl bg-white p-6 shadow-sm"
            >
                <div className="flex items-center gap-3">
                    <ShieldAlert className="text-hotel-navy" />
                    <div>
                        <h2 className="text-lg font-semibold">
                            Security settings
                        </h2>
                        <p className="text-sm text-gray-500">
                            Changes are recorded in this audit log.
                        </p>
                    </div>
                </div>
                <div className="mt-5 grid gap-5 md:grid-cols-2">
                    <label className="text-sm font-medium">
                        Audit retention
                        <select
                            className="mt-2 w-full rounded-md border p-2"
                            value={settings.data.retention_days}
                            onChange={(e) =>
                                settings.setData(
                                    "retention_days",
                                    e.target.value,
                                )
                            }
                        >
                            {[30, 90, 180, 365, 730].map((v) => (
                                <option key={v} value={v}>
                                    {v} days
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex items-center gap-3 rounded-lg border p-4">
                        <input
                            type="checkbox"
                            checked={settings.data.two_factor_enabled}
                            onChange={(e) =>
                                settings.setData(
                                    "two_factor_enabled",
                                    e.target.checked,
                                )
                            }
                        />
                        <span>
                            <strong className="block text-sm">
                                Email-code two-factor authentication
                            </strong>
                            <span className="text-xs text-gray-500">
                                Require a code when you sign in as an
                                administrator.
                            </span>
                        </span>
                    </label>
                </div>
                <div className="mt-6 border-t pt-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">Operations alerts</h3>
                            <p className="text-sm text-gray-500">Choose when this property should notify its administrators.</p>
                        </div>
                        <Button type="button" variant="outline" onClick={() => router.post(route("dashboard.security.test-alert"), {}, { preserveScroll: true })}>Send test alert</Button>
                    </div>
                    <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <label className="flex items-center gap-3 rounded-lg border p-4 text-sm font-medium"><input type="checkbox" checked={settings.data.alerts_enabled} onChange={e => settings.setData("alerts_enabled", e.target.checked)} />Enable operations alerts</label>
                        <label className="flex items-center gap-3 rounded-lg border p-4 text-sm font-medium"><input type="checkbox" checked={settings.data.email_enabled} onChange={e => settings.setData("email_enabled", e.target.checked)} />Send alert emails</label>
                        <label className="text-sm font-medium">Low battery threshold (%)<Input className="mt-2" type="number" min="5" max="50" value={settings.data.low_battery_threshold} onChange={e => settings.setData("low_battery_threshold", e.target.value)} /></label>
                        <label className="text-sm font-medium">Lock stale after (minutes)<Input className="mt-2" type="number" min="5" max="1440" value={settings.data.lock_offline_minutes} onChange={e => settings.setData("lock_offline_minutes", e.target.value)} /></label>
                        <label className="text-sm font-medium">FCM failures per hour<Input className="mt-2" type="number" min="1" max="100" value={settings.data.fcm_failure_threshold} onChange={e => settings.setData("fcm_failure_threshold", e.target.value)} /></label>
                        <label className="text-sm font-medium">Repeat alert after (minutes)<Input className="mt-2" type="number" min="5" max="1440" value={settings.data.alert_cooldown_minutes} onChange={e => settings.setData("alert_cooldown_minutes", e.target.value)} /></label>
                        <label className="text-sm font-medium md:col-span-2">Additional recipient emails<Input className="mt-2" type="text" placeholder="ops@example.com, owner@example.com" value={settings.data.alert_emails} onChange={e => settings.setData("alert_emails", e.target.value)} /></label>
                    </div>
                    <div className="mt-5 flex flex-wrap gap-3">
                        {["super_admin", "manager"].map(role => <label key={role} className="flex items-center gap-2 rounded-lg border px-4 py-3 text-sm"><input type="checkbox" checked={settings.data.alert_roles.includes(role)} onChange={e => settings.setData("alert_roles", e.target.checked ? [...settings.data.alert_roles, role] : settings.data.alert_roles.filter(item => item !== role))} />{role.replace("_", " ")}</label>)}
                    </div>
                    {Object.values(settings.errors).length > 0 && <p className="mt-4 text-sm text-red-600">{String(Object.values(settings.errors)[0])}</p>}
                </div>
                <div className="mt-5 flex justify-end">
                    <Button disabled={settings.processing}>
                        Save security settings
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
