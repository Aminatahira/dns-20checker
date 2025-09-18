/**
 * Shared code between client and server
 * Useful to share types between client and server
 * and/or small pure JS functions that can be used on both client and server
 */

/**
 * Example response type for /api/demo
 */
export interface DemoResponse {
  message: string;
}

export type DnsRecordType =
  | "A"
  | "AAAA"
  | "CNAME"
  | "MX"
  | "NS"
  | "TXT"
  | "SRV"
  | "SOA"
  | "CAA"
  | "PTR"
  | "DNSKEY"
  | "DS";

export interface ResolveQuery {
  domain: string;
  types: DnsRecordType[];
  provider?: "system" | "cloudflare" | "google";
}

export interface ResolveResponse {
  domain: string;
  provider: string;
  results: Record<string, unknown>;
}

export interface BulkResolveBody {
  domains: string[];
  types: DnsRecordType[];
  provider?: "system" | "cloudflare" | "google";
}

export interface BulkResolveResponse {
  provider: string;
  results: Record<string, Record<string, unknown>>;
}

export interface WhoisResponse {
  server?: string;
  raw: string;
}
