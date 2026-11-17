// Adapted from @tarhak/save-button — دکمهٔ وضعیت‌دار برای کارهای طولانی (آماده / در حال کار / انجام شد).
import { cn } from "@/lib/utils";
import { motion, AnimatePresence } from "motion/react";
import { Tick02Icon } from "@hugeicons/core-free-icons";
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react";
import { useEffect, useRef, useState } from "react";

export type StatusButtonState = "idle" | "loading" | "success";

export type StatusButtonProps = {
  /** کار طولانی؛ رد شدن Promise دکمه را به حالت آماده برمی‌گرداند. */
  onAction: () => Promise<void>;
  label: string;
  loadingLabel: string;
  successLabel: string;
  icon?: IconSvgElement;
  variant?: "primary" | "outline";
  disabled?: boolean;
  className?: string;
};

export function SaveButton({
  onAction,
  label,
  loadingLabel,
  successLabel,
  icon,
  variant = "primary",
  disabled = false,
  className,
}: StatusButtonProps) {
  const [status, setStatus] = useState<StatusButtonState>("idle");
  const mounted = useRef(true);
  useEffect(() => () => {
    mounted.current = false;
  }, []);

  const handleClick = async () => {
    setStatus("loading");
    try {
      await onAction();
      if (!mounted.current) return;
      setStatus("success");
      window.setTimeout(() => mounted.current && setStatus("idle"), 2000);
    } catch {
      if (mounted.current) setStatus("idle");
    }
  };

  const text = status === "idle" ? label : status === "loading" ? loadingLabel : successLabel;

  return (
    <div
      dir="rtl"
      lang="fa"
      className={cn("relative inline-flex group font-[family-name:var(--font-estedad),Tahoma,Arial,sans-serif] tracking-normal", className)}
    >
      <motion.button
        type="button"
        onClick={() => void handleClick()}
        whileTap={status === "idle" && !disabled ? { scale: 0.97 } : undefined}
        aria-busy={status === "loading"}
        className={cn(
          "relative inline-flex h-11 w-full min-w-[140px] items-center justify-center gap-2 rounded-full border px-6 text-sm font-medium transition-colors duration-300 disabled:opacity-100",
          status !== "idle"
            ? "cursor-not-allowed border-border bg-secondary text-secondary-foreground shadow-sm"
            : variant === "primary"
              ? "border-primary bg-primary text-primary-foreground shadow-[0_8px_20px_-10px_hsl(var(--primary))] hover:bg-primary/90"
              : "border-border bg-card text-foreground hover:bg-accent",
          disabled && status === "idle" && "cursor-not-allowed opacity-50",
        )}
        disabled={disabled || status !== "idle"}
      >
        {icon && status === "idle" ? <HugeiconsIcon icon={icon} size={18} /> : null}
        <span className="relative flex min-h-[1.25em] items-center justify-center overflow-hidden">
          <AnimatePresence mode="popLayout" initial={false}>
            <motion.span
              key={status}
              layout
              initial={{ opacity: 0, y: 8, filter: "blur(4px)" }}
              animate={{ opacity: 1, y: 0, filter: "blur(0px)" }}
              exit={{ opacity: 0, y: -8, filter: "blur(4px)" }}
              transition={{
                type: "spring",
                stiffness: 500,
                damping: 30,
                mass: 1,
              }}
              className="inline-block whitespace-nowrap"
            >
              {text}
            </motion.span>
          </AnimatePresence>
        </span>
      </motion.button>

      {/* Status Indicator */}
      <div className={cn("absolute -top-1 -end-1 z-10 pointer-events-none")}>
        <AnimatePresence mode="wait">
          {status !== "idle" && (
            <motion.div
              initial={{ opacity: 0, scale: 0, x: -8, filter: "blur(4px)" }}
              animate={{ opacity: 1, scale: 1, x: 0, filter: "blur(0px)" }}
              exit={{ opacity: 0, scale: 0, x: -8, filter: "blur(4px)" }}
              transition={{ type: "spring", stiffness: 300, damping: 20 }}
              className={cn(
                "relative flex size-6 items-center justify-center overflow-visible rounded-full ring-3",
                status === "success"
                  ? "bg-primary text-primary-foreground ring-background"
                  : "bg-secondary text-secondary-foreground ring-background"
              )}
            >
              <AnimatePresence mode="popLayout">
                {status === "loading" && (
                  <motion.div
                    key="loader"
                    initial={{ opacity: 1 }}
                    animate={{ opacity: 1 }}
                    exit={{ scale: 0, opacity: 0 }}
                    transition={{ duration: 0.2 }}
                    className="absolute inset-0 flex items-center justify-center"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      aria-hidden
                    >
                      <path
                        fill="currentColor"
                        d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z"
                        opacity=".5"
                      />
                      <path
                        fill="currentColor"
                        d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"
                      >
                        <animateTransform
                          attributeName="transform"
                          dur="1s"
                          from="0 12 12"
                          repeatCount="indefinite"
                          to="360 12 12"
                          type="rotate"
                        />
                      </path>
                    </svg>
                  </motion.div>
                )}
                {status === "success" && (
                  <motion.div
                    key="check"
                    initial={{ scale: 0, opacity: 0, filter: "blur(4px)" }}
                    animate={{ scale: 1, opacity: 1, filter: "blur(0px)" }}
                    exit={{ scale: 0, opacity: 0, filter: "blur(4px)" }}
                    transition={{ type: "spring", stiffness: 500, damping: 25 }}
                    className="absolute inset-0 flex items-center justify-center"
                  >
                    <HugeiconsIcon icon={Tick02Icon} className="size-4" />
                  </motion.div>
                )}
              </AnimatePresence>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}
