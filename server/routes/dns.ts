import { RequestHandler } from "express";
import dns from "node:dns";
import { setTimeout as delay } from "node:timers/promises";
import net from "node:net";

const resolver = dns.promises;

const SUPPORTED_SYSTEM_TYPES = [
  "A",
  "AAAA",
  "CNAME",
  "MX",
  "NS",
  "TXT",
  "SRV",
  "SOA",
  "CAA",
  // PTR handled specially via dns.reverse
];

async function resolveByType(domain: string, type: string) {
  const t = type.toUpperCase();
  try {
    switch (t) {
      case "A":
        return await resolver.resolve4(domain);
      case "AAAA":
        return await resolver.resolve6(domain);
      case "CNAME":
        return await resolver.resolveCname(domain);
      case "MX":
        return await resolver.resolveMx(domain);
      case "NS":
        return await resolver.resolveNs(domain);
      case "TXT":
        return await resolver.resolveTxt(domain);
      case "SRV":
        return await resolver.resolveSrv(domain);
      case "SOA":
        return await resolver.resolveSoa(domain);
      case "CAA":
        return await resolver.resolveCaa(domain);
      case "PTR": {
        // For PTR, domain is expected to be an IP address
        return await resolver.reverse(domain);
      }
      case "DNSKEY":
      case "DS": {
        // Not guaranteed to be supported natively; fall back to DoH
        return await dohQuery(domain, t);
      }
      default: {
        // Attempt generic resolve as a fallback
        try {
          // @ts-expect-error rrtype is dynamic
          return await resolver.resolve(domain, t);
        } catch {
          return await dohQuery(domain, t);
        }
      }
    }
  } catch (err) {
    // Try DoH fallback on failure (except PTR which needs IP)
    if (t !== "PTR") {
      try {
        return await dohQuery(domain, t);
      } catch (e) {
        throw err; // return original error if DoH also fails
      }
    }
    throw err;
  }
}

async function dohQuery(name: string, type: string, provider: "cloudflare" | "google" = "cloudflare") {
  const endpoint = provider === "google"
    ? `https://dns.google/resolve?name=${encodeURIComponent(name)}&type=${encodeURIComponent(type)}`
    : `https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(name)}&type=${encodeURIComponent(type)}`;

  const res = await fetch(endpoint, {
    headers: { Accept: "application/dns-json" },
  });
  if (!res.ok) {
    throw new Error(`DoH request failed: ${res.status} ${res.statusText}`);
  }
  const json = await res.json();
  return json;
}

export const handleResolve: RequestHandler = async (req, res) => {
  const domain = String(req.query.domain || "").trim();
  const provider = String((req.query.provider || "system")).toLowerCase();
  const types = String(req.query.types || "A,AAAA,MX,TXT,NS,CNAME,SOA,SRV,CAA,DNSKEY,DS").split(",").map((s) => s.trim()).filter(Boolean);
  if (!domain) return res.status(400).json({ error: "Missing domain" });

  const results: Record<string, unknown> = {};
  for (const t of types) {
    try {
      if (provider !== "system" || (t.toUpperCase() === "DNSKEY" || t.toUpperCase() === "DS")) {
        results[t.toUpperCase()] = await dohQuery(domain, t.toUpperCase(), provider === "google" ? "google" : "cloudflare");
      } else if (t.toUpperCase() === "PTR") {
        results[t.toUpperCase()] = await resolver.reverse(domain);
      } else if (SUPPORTED_SYSTEM_TYPES.includes(t.toUpperCase())) {
        results[t.toUpperCase()] = await resolveByType(domain, t.toUpperCase());
      } else {
        results[t.toUpperCase()] = await dohQuery(domain, t.toUpperCase());
      }
    } catch (e: any) {
      results[t.toUpperCase()] = { error: String(e?.message || e) };
    }
    // tiny delay to be friendly
    await delay(5);
  }

  res.json({ domain, provider: provider || "system", results });
};

export const handleBulk: RequestHandler = async (req, res) => {
  const body = req.body || {};
  const domains: string[] = Array.isArray(body.domains) ? body.domains : [];
  const provider = String((body.provider || "system")).toLowerCase();
  const types: string[] = Array.isArray(body.types) && body.types.length
    ? body.types
    : ["A", "AAAA", "MX", "TXT", "NS"];

  if (!domains.length) return res.status(400).json({ error: "domains array required" });

  const out: Record<string, any> = {};
  await Promise.all(domains.map(async (d) => {
    const item: Record<string, unknown> = {};
    for (const t of types) {
      try {
        item[t.toUpperCase()] = await resolveByType(d, t.toUpperCase());
      } catch (e: any) {
        item[t.toUpperCase()] = { error: String(e?.message || e) };
      }
      await delay(5);
    }
    out[d] = item;
  }));

  res.json({ provider: provider || "system", results: out });
};

function whoisQuery(server: string, query: string): Promise<string> {
  return new Promise((resolve, reject) => {
    const socket = net.createConnection(43, server);
    let data = "";
    socket.setTimeout(10000);
    socket.on("connect", () => {
      socket.write(query + "\r\n");
    });
    socket.on("data", (chunk) => (data += chunk.toString("utf8")));
    socket.on("timeout", () => {
      socket.destroy();
      reject(new Error("WHOIS timeout"));
    });
    socket.on("error", reject);
    socket.on("close", () => resolve(data));
  });
}

async function whoisLookup(query: string): Promise<{ server?: string; raw: string }> {
  // First ask IANA for the authoritative WHOIS server for the TLD
  const tld = query.split(".").pop() || query;
  try {
    const iana = await whoisQuery("whois.iana.org", tld);
    const match = /whois:\s*([^\s]+)/i.exec(iana);
    const server = match?.[1] || "whois.iana.org";
    const raw = await whoisQuery(server, query);
    return { server, raw };
  } catch (e: any) {
    // Fallback to whois.iana.org direct
    const raw = await whoisQuery("whois.iana.org", query);
    return { server: "whois.iana.org", raw };
  }
}

export const handleWhois: RequestHandler = async (req, res) => {
  const q = String(req.query.query || "").trim();
  if (!q) return res.status(400).json({ error: "Missing query" });
  try {
    const result = await whoisLookup(q);
    res.json(result);
  } catch (e: any) {
    res.status(500).json({ error: String(e?.message || e) });
  }
};

export const handleDoh: RequestHandler = async (req, res) => {
  const name = String(req.query.name || "").trim();
  const type = String(req.query.type || "A").toUpperCase();
  const provider = String((req.query.provider || "cloudflare")).toLowerCase();
  if (!name) return res.status(400).json({ error: "Missing name" });
  try {
    const json = await dohQuery(name, type, provider === "google" ? "google" : "cloudflare");
    res.json(json);
  } catch (e: any) {
    res.status(500).json({ error: String(e?.message || e) });
  }
};
