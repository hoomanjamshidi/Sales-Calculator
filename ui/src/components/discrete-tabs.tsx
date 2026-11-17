// Adapted from @tarhak/discrete-tabs — کنترل‌شده با فهرست تب دلخواه.
import { motion, MotionConfig } from "motion/react";
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react";
import clsx from "clsx";

export type DiscreteTab<T extends string> = { id: T; title: string; icon: IconSvgElement };

const ROW_EASE: [number, number, number, number] = [0.215, 0.61, 0.355, 1];

export default function DiscreteTabs<T extends string>({
  tabs,
  value,
  onChange,
  label = "تب‌های فشرده",
  className,
}: {
  tabs: readonly DiscreteTab<T>[];
  value: T;
  onChange: (id: T) => void;
  label?: string;
  className?: string;
}) {
  return (
    <section
      dir="rtl"
      lang="fa"
      role="group"
      aria-label={label}
      className={clsx(
        "flex items-center gap-2 fill-muted-foreground/70 font-[family-name:var(--font-estedad),Tahoma,Arial,sans-serif] tracking-normal",
        className,
      )}
    >
      <MotionConfig
        transition={{ type: "spring", duration: 0.85, bounce: 0.35 }}
      >
        {tabs.map((tab) => (
          <TabButton
            key={tab.id}
            title={tab.title}
            icon={tab.icon}
            isActive={value === tab.id}
            onSelect={() => onChange(tab.id)}
          />
        ))}
      </MotionConfig>
    </section>
  );
}

function TabButton({
  title,
  icon,
  isActive,
  onSelect,
}: {
  title: string;
  icon: IconSvgElement;
  isActive: boolean;
  onSelect: () => void;
}) {
  return (
    <motion.button
      type="button"
      layout
      transition={{
        layout: {
          type: "spring",
          bounce: 0.2,
          duration: 0.55,
          ease: ROW_EASE,
        },
      }}
      onClick={onSelect}
      aria-pressed={isActive}
      aria-label={title}
      title={title}
      className={clsx(
        "flex h-10 cursor-pointer items-center gap-x-2 overflow-hidden border border-border outline-none",
        "focus-visible:ring-2 focus-visible:ring-ring",
        isActive
          ? "bg-card px-4 text-foreground shadow-sm"
          : "bg-background px-3 text-muted-foreground hover:bg-accent hover:text-foreground",
      )}
      style={{ borderRadius: isActive ? 14 : 40, borderWidth: 1 }}
    >
      <motion.span layout className="shrink-0">
        <HugeiconsIcon icon={icon} size={18} />
      </motion.span>

      {isActive ? (
        <motion.span
          initial={{ opacity: 0, x: 8 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{
            type: "spring",
            bounce: 0.1,
            duration: 0.25,
            ease: ROW_EASE,
          }}
          className="whitespace-nowrap text-sm font-medium"
        >
          {title}
        </motion.span>
      ) : null}
    </motion.button>
  );
}
