# Visual Editor Issue Reorganization - Complete

## Summary
✅ **31 new granular issues created** (Issues #43-73)
📊 **Organized into 7 implementation epics**
⏱️ **Effort estimates: 1-7 days per issue**
🎯 **Aligned with Gutenberg analysis and 26-week roadmap**

**Reorganization Date:** 2026-02-16

---

## New Issue Structure

### **Epic 1: Foundation** (Weeks 1-2) - 5 issues
Foundation features that everything else depends on:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #43 | Keyboard Shortcuts System | ⭐⭐⭐⭐⭐ Critical | 3-5d | To Do |
| #44 | Undo/Redo Manager with Staging | ⭐⭐⭐⭐⭐ Critical | 3-4d | To Do |
| #45 | Redux Store Pattern for State Management | ⭐⭐⭐⭐⭐ Critical | 5-7d | To Do |
| #46 | Port Essential Composition Hooks (30+ hooks) | ⭐⭐⭐⭐⭐ Critical | 5-7d | To Do |
| #47 | Media Query Hook for Responsive Design | ⭐⭐⭐⭐ High | 2-3d | To Do |

**Total Effort:** 18-26 days (3-4 weeks)

---

### **Epic 2: Core Editor** (Weeks 3-6) - 6 issues
Core editor UI components and accessibility:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #48 | Focus Management HOCs and Utilities | ⭐⭐⭐⭐ Critical | 2-3d | To Do |
| #49 | ARIA Live Regions for Screen Readers | ⭐⭐⭐⭐ Critical | 2-3d | To Do |
| #50 | Popover Component with Smart Positioning | ⭐⭐⭐⭐ High | 4-5d | To Do |
| #51 | Toolbar Component System | ⭐⭐⭐⭐ High | 3-4d | To Do |
| #52 | Panel System for Block Inspector | ⭐⭐⭐⭐ High | 3-4d | To Do |
| #53 | Animation System with Reduced Motion | ⭐⭐⭐ Medium | 2-3d | To Do |

**Total Effort:** 16-22 days (3-4 weeks)

---

### **Epic 3: Block Interaction** (Weeks 7-10) - 5 issues
Drag-and-drop, clipboard, selection:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #54 | Drop Zone Handler with Visual Feedback | ⭐⭐⭐⭐⭐ Critical | 5-7d | To Do |
| #55 | Clipboard Handling for Copy/Paste | ⭐⭐⭐⭐ High | 3-4d | To Do |
| #56 | Selection Tracking System | ⭐⭐⭐⭐ High | 3-4d | To Do |
| #57 | Block Mover Component | ⭐⭐⭐ Medium | 2-3d | To Do |
| #58 | Block Duplication Feature | ⭐⭐⭐ Medium | 2-3d | To Do |

**Total Effort:** 15-21 days (3 weeks)

---

### **Epic 4: Block Controls** (Weeks 11-14) - 5 issues
Extensibility and advanced controls:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #59 | Block Controls Slot/Fill System | ⭐⭐⭐⭐ High | 5-7d | To Do |
| #60 | UnitControl Component | ⭐⭐⭐⭐ High | 2-3d | To Do |
| #61 | BoxControl Component for Spacing | ⭐⭐⭐⭐ High | 3-4d | To Do |
| #62 | AlignmentControl Components | ⭐⭐⭐⭐ High | 2-3d | To Do |
| #63 | LinkControl Component | ⭐⭐⭐⭐ High | 4-5d | To Do |

**Total Effort:** 16-22 days (3-4 weeks)

---

### **Epic 5: Styling & Locking** (Weeks 15-18) - 2 issues
Block locking and color system:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #69 | Block Locking System | ⭐⭐⭐⭐ High | 4-5d | To Do |
| #70 | Color System with Contrast Checking | ⭐⭐⭐⭐ High | 4-5d | To Do |

**Total Effort:** 8-10 days (2 weeks)

**Note:** #69 replaces the old #33 (Permissions & Locking)

---

### **Epic 6: Media Blocks** (Phase 2) - 5 issues
Breaking down existing #25 into granular blocks:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #64 | Video Block (YouTube, Vimeo, Self-Hosted) | ⭐⭐⭐⭐ High | 4-5d | To Do |
| #65 | Audio Block | ⭐⭐⭐ Medium | 3-4d | To Do |
| #66 | File Download Block | ⭐⭐⭐ Medium | 2-3d | To Do |
| #67 | Gallery Block | ⭐⭐⭐⭐ High | 5-6d | To Do |
| #68 | Code Block with Syntax Highlighting | ⭐⭐⭐ Medium | 3-4d | To Do |

**Total Effort:** 17-22 days (3-4 weeks)

**Note:** Issue #25 updated to reference these child issues

---

### **Epic 7: Layout & Interactive Blocks** - 3 issues
Essential blocks for content creation:

| # | Title | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| #71 | Button Block | ⭐⭐⭐⭐ High | 3-4d | To Do |
| #72 | Spacer Block | ⭐⭐⭐ Medium | 2-3d | To Do |
| #73 | Divider Block | ⭐⭐⭐ Medium | 1-2d | To Do |

**Total Effort:** 6-9 days (1-2 weeks)

---

## Total New Issues by Priority

| Priority | Count | Percentage |
|----------|-------|------------|
| ⭐⭐⭐⭐⭐ Critical | 5 | 16% |
| ⭐⭐⭐⭐ High | 17 | 55% |
| ⭐⭐⭐ Medium | 9 | 29% |

**Total:** 31 issues

---

## Total Effort Estimate

| Epic | Days (Range) | Weeks |
|------|--------------|-------|
| Foundation | 18-26 | 3-4 |
| Core Editor | 16-22 | 3-4 |
| Block Interaction | 15-21 | 3 |
| Block Controls | 16-22 | 3-4 |
| Styling & Locking | 8-10 | 2 |
| Media Blocks | 17-22 | 3-4 |
| Layout/Interactive | 6-9 | 1-2 |

**Grand Total:** 96-132 days (~19-26 weeks for solo dev)

This aligns perfectly with the **26-week Gutenberg roadmap** from GUTENBERG-ANALYSIS-AND-INTEGRATION-PLAN.md!

---

## Issues to Update/Close

### ✅ Closed:
- **#33 (Permissions & Locking)** → Replaced by #69 (Block Locking System)

### ✏️ Updated:
- **#25 (Media Blocks)** → Added references to child issues #64-68

### 📋 Keep for Future Phases (3-6):
- #28 (Embed Blocks) - Phase 3/4 placeholder
- #30 (Template System) - Phase 3
- #31 (Template Parts) - Phase 3
- #32 (Global Styles System) - Phase 3
- #34 (Revision History) - Phase 3
- #35 (AI Assistant) - Phase 5
- #36 (A/B Testing) - Phase 5
- #37 (Accessibility Scanner) - Phase 4
- #40 (SEO Integration) - Phase 4
- #41 (Presence Awareness) - Phase 6

---

## Implementation Order

**Start Here (Foundation):**
1. #43 - Keyboard Shortcuts System
2. #44 - Undo/Redo Manager
3. #45 - Redux Store Pattern
4. #46 - Essential Composition Hooks
5. #47 - Media Query Hook

**Then (Core Editor):**
6. #48 - Focus Management
7. #49 - ARIA Live Regions
8. #50 - Popover Component
9. #51 - Toolbar System
10. #52 - Panel System
11. #53 - Animation System

**Continue (Block Interaction):**
12. #54 - Drop Zone Handler
13. #55 - Clipboard Handling
14. #56 - Selection Tracking
15. #57 - Block Mover
16. #58 - Block Duplication

**And so on...** following the epic order above.

---

## Labels Created

### Epic Labels:
- `Epic::Foundation`
- `Epic::Core Editor`
- `Epic::Block Interaction`
- `Epic::Block Controls`
- `Epic::Styling & Locking`
- `Epic::Media Blocks`
- `Epic::Layout Blocks`
- `Epic::Interactive Blocks`

### Effort Labels:
- `Effort::1-2d`
- `Effort::2-3d`
- `Effort::3-4d`
- `Effort::4-5d`
- `Effort::5-6d`
- `Effort::5-7d`

### Block Type Labels:
- `Block::Video`
- `Block::Audio`
- `Block::File`
- `Block::Gallery`
- `Block::Code`
- `Block::Button`
- `Block::Spacer`
- `Block::Divider`

---

## Benefits of This Reorganization

✅ **Clear Dependencies:** Each issue lists what it depends on
✅ **Realistic Estimates:** 1-7 day efforts, not multi-week epics
✅ **Gutenberg-Aligned:** Based on proven implementation patterns from 2,251-line analysis
✅ **Prioritized:** Critical path clearly identified (Foundation → Core → Interaction → Controls)
✅ **Traceable:** Each issue references specific documentation files
✅ **Actionable:** Can start immediately on #43 without blockers
✅ **Epic-Organized:** Grouped by feature area for better project management
✅ **Time-Boxed:** All estimates are realistic single-developer day ranges

---

## Quick Reference

**GitLab Project:** https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor

**View Issues by Epic:**
- Foundation: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Foundation
- Core Editor: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Core%20Editor
- Block Interaction: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Block%20Interaction
- Block Controls: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Block%20Controls
- Styling & Locking: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Styling%20%26%20Locking
- Media Blocks: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Media%20Blocks
- Layout Blocks: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Layout%20Blocks
- Interactive Blocks: https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-visual-editor/-/issues?label_name[]=Epic::Interactive%20Blocks

---

## Related Documentation

- `IMPLEMENTATION_STATUS.md` - Current progress and Phase 2 priorities
- `GUTENBERG-ANALYSIS-AND-INTEGRATION-PLAN.md` - 2,251-line comprehensive analysis
- `COMPONENT-PRIORITY-LIST.md` - 54 components prioritized
- `COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md` - What you have vs. what to build
- `plans/01-comprehensive-plan.md` - Executive overview of all 6 phases
- `plans/03-block-system.md` - 25+ core blocks with field types

Your visual editor package now has a **crystal-clear implementation roadmap**! 🎉
