import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { ALL_TYPES, type DnsRecordType } from "@/features/dns/types";
import { resolveDns, bulkResolve, whois } from "@/features/dns/api";
import { Cloud, Globe, Server } from "lucide-react";
import { toast } from "sonner";

export default function Index() {
  const [domain, setDomain] = useState("example.com");
  const [selectedTypes, setSelectedTypes] = useState<DnsRecordType[]>(["A", "AAAA", "MX", "TXT", "NS"]);
  const [provider, setProvider] = useState<"system" | "cloudflare" | "google">("system");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<any | null>(null);
  const [whoisData, setWhoisData] = useState<string | null>(null);

  const [bulkInput, setBulkInput] = useState("google.com\ncloudflare.com\nopenai.com");
  const [bulkLoading, setBulkLoading] = useState(false);
  const [bulkResult, setBulkResult] = useState<any | null>(null);

  const allChecked = useMemo(
    () => selectedTypes.length === ALL_TYPES.length,
    [selectedTypes],
  );
  const toggleAll = (checked: boolean) => {
    setSelectedTypes(checked ? [...ALL_TYPES] as DnsRecordType[] : []);
  };
  const toggleType = (t: DnsRecordType, checked: boolean) => {
    setSelectedTypes((prev) => checked ? Array.from(new Set([...prev, t])) as DnsRecordType[] : prev.filter((x) => x !== t));
  };

  const runLookup = async () => {
    if (!domain) return toast.error("Please enter a domain or IP");
    setLoading(true);
    setWhoisData(null);
    try {
      const data = await resolveDns({ domain, types: selectedTypes, provider });
      setResult(data);
    } catch (e) {
      const msg = String((e as any)?.message || e);
      toast.error(msg);
      setResult({ error: msg });
    } finally {
      setLoading(false);
    }
  };
  const runWhois = async () => {
    if (!domain) return toast.error("Please enter a domain");
    setLoading(true);
    try {
      const data = await whois(domain);
      setWhoisData(data.raw);
    } catch (e) {
      const msg = String((e as any)?.message || e);
      toast.error(msg);
      setWhoisData(msg);
    } finally {
      setLoading(false);
    }
  };
  const runBulk = async () => {
    const domains = bulkInput.split(/\s+/).map((d) => d.trim()).filter(Boolean);
    if (!domains.length) return toast.error("Enter at least one domain");
    setBulkLoading(true);
    try {
      const data = await bulkResolve({ domains, types: selectedTypes, provider });
      setBulkResult(data);
    } catch (e) {
      const msg = String((e as any)?.message || e);
      toast.error(msg);
      setBulkResult({ error: msg });
    } finally {
      setBulkLoading(false);
    }
  };

  return (
    <div className="relative">
      <section className="relative overflow-hidden">
        <div className="absolute inset-0 -z-10 bg-gradient-to-br from-brand-700 via-brand-600 to-brand-400" />
        <div className="absolute -z-10 inset-0 opacity-30" aria-hidden>
          <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[560px] w-[560px] rounded-full bg-white blur-3xl" />
        </div>
        <div className="container py-14 md:py-20 text-white">
          <h1 className="text-3xl md:text-5xl font-extrabold tracking-tight">Advanced DNS Records Checker</h1>
          <p className="mt-3 md:mt-4 text-white/80 max-w-2xl">Run fast, comprehensive lookups across A, AAAA, MX, TXT, NS, CNAME, PTR, SRV, SOA, CAA, DNSKEY, and DS. Choose resolver and run bulk checks.</p>

          <div className="mt-8 grid gap-4 md:grid-cols-[minmax(0,440px)_auto_auto] md:items-center">
            <div className="">
              <Input value={domain} onChange={(e) => setDomain(e.target.value)} placeholder="Enter domain (or IP for PTR)" className="bg-white/10 placeholder:text-white/70 text-white border-white/20 focus-visible:ring-white/70" />
            </div>
            <div className="flex items-center gap-1.5 text-sm rounded-lg border border-white/20 p-1 bg-white/10">
              <button aria-pressed={provider==="system"} className={"inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors "+(provider==="system"?"bg-white/20 text-white":"text-white/80 hover:bg-white/10")} onClick={() => setProvider("system")}>
                <Server className="h-4 w-4" /> System
              </button>
              <button aria-pressed={provider==="cloudflare"} className={"inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors "+(provider==="cloudflare"?"bg-white/20 text-white":"text-white/80 hover:bg-white/10")} onClick={() => setProvider("cloudflare")}>
                <Cloud className="h-4 w-4" /> Cloudflare
              </button>
              <button aria-pressed={provider==="google"} className={"inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors "+(provider==="google"?"bg-white/20 text-white":"text-white/80 hover:bg-white/10")} onClick={() => setProvider("google")}>
                <Globe className="h-4 w-4" /> Google
              </button>
            </div>
            <div className="flex items-center gap-2">
              <Button size="lg" onClick={runLookup} disabled={loading} className="bg-white text-brand-800 hover:bg-white/90 shadow-lg">{loading ? "Checking…" : "Check DNS"}</Button>
              <Button size="lg" variant="outline" onClick={runWhois}>WHOIS</Button>
            </div>
          </div>

          <div className="mt-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 text-sm">
            <label className="inline-flex items-center gap-2 bg-white/10 rounded-md px-3 py-2 border border-white/10 hover:bg-white/15">
              <Checkbox checked={allChecked} onCheckedChange={(c) => toggleAll(Boolean(c))} />
              <span>All</span>
            </label>
            {ALL_TYPES.map((t) => (
              <label key={t} className="inline-flex items-center gap-2 bg-white/10 rounded-md px-3 py-2 border border-white/10 hover:bg-white/15">
                <Checkbox checked={selectedTypes.includes(t)} onCheckedChange={(c) => toggleType(t, Boolean(c))} />
                <span>{t}</span>
              </label>
            ))}
          </div>
        </div>
      </section>

      <section className="container mx-auto -mt-10 md:-mt-12 pb-16">
        <Tabs defaultValue="single" className="">
          <TabsList className="bg-muted/60">
            <TabsTrigger value="single">Single Lookup</TabsTrigger>
            <TabsTrigger value="bulk">Bulk Lookup</TabsTrigger>
          </TabsList>

          <TabsContent value="single">
            <Card className="shadow-xl">
              <CardHeader>
                <CardTitle>Results</CardTitle>
                <CardDescription>Provider: <span className="font-mono">{provider}</span>{result?.domain ? <> · Domain: <span className="font-mono">{result.domain}</span></> : null}</CardDescription>
              </CardHeader>
              <CardContent>
                {loading && (
                  <div className="space-y-3">
                    <div className="h-4 w-40 bg-muted rounded animate-pulse" />
                    <div className="h-32 bg-muted rounded animate-pulse" />
                  </div>
                )}
                {!loading && !result && !whoisData && (
                  <p className="text-muted-foreground">Run a lookup to see results.</p>
                )}

                {whoisData && (
                  <div className="mt-4">
                    <h3 className="font-semibold mb-2">WHOIS</h3>
                    <pre className="whitespace-pre-wrap rounded-md bg-muted p-4 text-xs overflow-auto max-h-[420px]">{whoisData}</pre>
                  </div>
                )}

                {jsonView && result ? (
                  <pre className="rounded-md bg-muted p-4 text-xs overflow-auto max-h-[560px]">{JSON.stringify(result, null, 2)}</pre>
                ) : null}

                {!jsonView && result?.results ? (
                  <div className="grid gap-6">
                    {Object.entries(result.results).map(([type, value]) => (
                      <div key={type}>
                        <h3 className="font-semibold mb-2">{type}</h3>
                        <RecordValueTable type={type} value={value} />
                      </div>
                    ))}
                  </div>
                ) : null}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="bulk">
            <Card className="shadow-xl">
              <CardHeader>
                <CardTitle>Bulk Lookup</CardTitle>
                <CardDescription>Enter one domain per line. Uses the same selected record types.</CardDescription>
              </CardHeader>
              <CardContent className="grid gap-4">
                <Textarea value={bulkInput} onChange={(e) => setBulkInput(e.target.value)} rows={6} placeholder="domain.com\nexample.org" />
                <div className="flex items-center gap-3">
                  <Button onClick={runBulk} disabled={bulkLoading}>{bulkLoading ? "Running…" : "Run Bulk"}</Button>
                </div>
                {bulkResult ? (
                  <div className="mt-2 grid gap-6">
                    {bulkResult.error ? (
                      <p className="text-destructive">{String(bulkResult.error)}</p>
                    ) : (
                      Object.entries<any>(bulkResult.results).map(([d, recordMap]) => (
                        <div key={d} className="rounded-lg border p-4">
                          <h3 className="font-semibold mb-2">{d}</h3>
                          {Object.entries(recordMap as Record<string, unknown>).map(([t, v]) => (
                            <div key={t} className="mb-4">
                              <div className="text-sm font-medium mb-1">{t}</div>
                              <RecordValueTable type={t} value={v} />
                            </div>
                          ))}
                        </div>
                      ))
                    )}
                  </div>
                ) : null}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </section>
    </div>
  );
}

function isObject(v: any) {
  return v && typeof v === "object" && !Array.isArray(v);
}

function RecordValueTable({ type, value }: { type: string; value: any }) {
  if (!value) return <p className="text-muted-foreground">No data.</p>;
  if (value.error) return <p className="text-destructive">{String(value.error)}</p>;

  // Cloudflare/Google DoH returns JSON objects, while system resolver returns arrays/objects.
  if (typeof value === "string" || typeof value === "number") {
    return <pre className="text-xs bg-muted rounded-md p-3">{String(value)}</pre>;
  }

  if (Array.isArray(value)) {
    const rows = value;
    return (
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>#</TableHead>
            <TableHead>Value</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row, idx) => (
            <TableRow key={idx}>
              <TableCell className="w-10 text-muted-foreground">{idx + 1}</TableCell>
              <TableCell>
                {isObject(row) ? (
                  <pre className="text-xs bg-muted rounded-md p-2 overflow-auto max-h-52">{JSON.stringify(row, null, 2)}</pre>
                ) : (
                  <span className="font-mono">{String(row)}</span>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    );
  }

  if (isObject(value)) {
    // Likely DoH response
    const obj = value as Record<string, any>;
    if (obj.Answer && Array.isArray(obj.Answer)) {
      return (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>name</TableHead>
              <TableHead>type</TableHead>
              <TableHead>TTL</TableHead>
              <TableHead>data</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {obj.Answer.map((a: any, idx: number) => (
              <TableRow key={idx}>
                <TableCell className="font-mono text-xs">{a.name}</TableCell>
                <TableCell className="font-mono text-xs">{a.type}</TableCell>
                <TableCell className="font-mono text-xs">{a.TTL}</TableCell>
                <TableCell className="font-mono text-xs break-all">{a.data}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      );
    }
    return <pre className="text-xs bg-muted rounded-md p-3 overflow-auto">{JSON.stringify(value, null, 2)}</pre>;
  }

  return <pre className="text-xs bg-muted rounded-md p-3">{String(value)}</pre>;
}
