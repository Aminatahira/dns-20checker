import { Link, Outlet, useLocation } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export default function Layout() {
  const location = useLocation();
  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-background">
      <header className="sticky top-0 z-40 w-full border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="container mx-auto flex h-16 items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-brand-600 to-brand-400 text-white font-bold">D</span>
            <span className="font-semibold text-lg tracking-tight">DNS Suite</span>
          </Link>
          <nav className="flex items-center gap-2">
            <Button asChild variant={location.pathname === "/" ? "default" : "secondary"}>
              <Link to="/">Checker</Link>
            </Button>
            {/* Future links can go here */}
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
        <div className="container mx-auto py-8 text-sm text-muted-foreground flex items-center justify-between">
          <p>© {new Date().getFullYear()} DNS Suite</p>
          <p className="hidden md:block">Advanced DNS tools with an elegant UI.</p>
        </div>
      </footer>
    </div>
  );
}
