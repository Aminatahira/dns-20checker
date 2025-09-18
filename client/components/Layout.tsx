import { Link as RouterLink, Outlet, useLocation } from "react-router-dom";
import { Link as RouterLink, Outlet, useLocation } from "react-router-dom";
import { Button } from "@/components/ui/button";

export default function Layout() {
  const location = useLocation();
  return (
    <div className="min-h-screen bg-[radial-gradient(1200px_600px_at_50%_-200px,theme(colors.brand.100/.6),transparent_60%)]">
      <header className="sticky top-0 z-40 w-full border-b border-border/60 bg-background/70 backdrop-blur supports-[backdrop-filter]:bg-background/50">
        <div className="container flex h-16 items-center justify-between">
          <RouterLink to="/" className="flex items-center gap-2">
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-brand-600 to-brand-400 text-white font-bold shadow-sm">D</span>
            <span className="font-semibold text-lg tracking-tight bg-gradient-to-r from-brand-700 to-brand-500 bg-clip-text text-transparent">DNS Suite</span>
          </RouterLink>
          <nav className="flex items-center gap-2">
            <Button asChild variant={location.pathname === "/" ? "default" : "secondary"}>
              <RouterLink to="/">Checker</RouterLink>
            </Button>
            <a
              href="https://builder.io/c/docs/projects"
              target="_blank"
              rel="noreferrer"
              className="text-sm text-muted-foreground hover:text-foreground"
            >
              Docs
            </a>
          </nav>
        </div>
      </header>
      <main>
        <Outlet />
      </main>
      <footer className="border-t mt-16">
        <div className="container py-8 text-sm text-muted-foreground flex items-center justify-between">
          <p>© {new Date().getFullYear()} DNS Suite</p>
          <p className="hidden md:block">Advanced DNS tools with an elegant UI.</p>
        </div>
      </footer>
    </div>
  );
}
