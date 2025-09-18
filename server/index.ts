import "dotenv/config";
import express from "express";
import cors from "cors";
import { handleDemo } from "./routes/demo";
import { handleResolve, handleBulk, handleWhois, handleDoh } from "./routes/dns";

export function createServer() {
  const app = express();

  // Middleware
  app.use(cors());
  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));

  // Example API routes
  app.get("/api/ping", (_req, res) => {
    const ping = process.env.PING_MESSAGE ?? "ping";
    res.json({ message: ping });
  });

  app.get("/api/demo", handleDemo);

  // DNS API
  const { handleResolve, handleBulk, handleWhois, handleDoh } = await import("./routes/dns");
  app.get("/api/dns/resolve", handleResolve);
  app.post("/api/dns/bulk", handleBulk);
  app.get("/api/dns/whois", handleWhois);
  app.get("/api/dns/doh", handleDoh);

  return app;
}
