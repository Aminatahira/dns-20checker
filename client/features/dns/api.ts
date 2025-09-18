import type {
  ResolveQuery,
  ResolveResponse,
  BulkResolveBody,
  BulkResolveResponse,
  WhoisResponse,
} from "@shared/api";

export async function resolveDns(params: ResolveQuery): Promise<ResolveResponse> {
  const url = new URL("/api/dns/resolve", window.location.origin);
  url.searchParams.set("domain", params.domain);
  url.searchParams.set("types", params.types.join(","));
  if (params.provider) url.searchParams.set("provider", params.provider);
  const res = await fetch(url.toString());
  if (!res.ok) throw new Error(`Resolve failed: ${res.status}`);
  return res.json();
}

export async function bulkResolve(body: BulkResolveBody): Promise<BulkResolveResponse> {
  const res = await fetch("/api/dns/bulk", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error(`Bulk resolve failed: ${res.status}`);
  return res.json();
}

export async function whois(query: string): Promise<WhoisResponse> {
  const url = new URL("/api/dns/whois", window.location.origin);
  url.searchParams.set("query", query);
  const res = await fetch(url.toString());
  if (!res.ok) throw new Error(`WHOIS failed: ${res.status}`);
  return res.json();
}

export async function doh(name: string, type: string, provider: "cloudflare" | "google" = "cloudflare") {
  const url = new URL("/api/dns/doh", window.location.origin);
  url.searchParams.set("name", name);
  url.searchParams.set("type", type);
  url.searchParams.set("provider", provider);
  const res = await fetch(url.toString());
  if (!res.ok) throw new Error(`DoH failed: ${res.status}`);
  return res.json();
}
