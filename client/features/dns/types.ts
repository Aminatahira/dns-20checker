import type { DnsRecordType } from "@shared/api";

export type { DnsRecordType };

export const ALL_TYPES: DnsRecordType[] = [
  "A",
  "AAAA",
  "CNAME",
  "MX",
  "NS",
  "TXT",
  "SRV",
  "SOA",
  "CAA",
  "PTR",
  "DNSKEY",
  "DS",
];
