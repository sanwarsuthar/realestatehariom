# Commission & Referral Distribution Rules

## Overview

This document describes the complete rules and logic for commission distribution and referral commission distribution in the system.

---

## Commission Distribution System

### Core Concepts

1. **Allocated Amount**: The commission amount allocated per unit (sqft/sqyd) for a property type in a project
   - Can be configured as **fixed** (e.g., ₹1,500 per sqyd) or **percentage** (e.g., 5% of property price)
   - Stored in `project.allocated_amount_config` per property type

2. **Slab System**: Commission rates are based on performance slabs (Slab1, Slab2, Slab3, Slab4, etc.)
   - Each slab has a commission percentage (e.g., Slab1 = 35%, Slab2 = 40%)
   - Slabs are determined by **total volume sold** (OWN sales + TEAM sales) for each property type
   - Slabs can have different commission rates per property type

3. **Progressive Commission**: When a sale spans multiple slab tiers, each portion is paid at the appropriate slab rate

---

## Direct Commission Distribution (Level 1)

### Calculation Formula

**Direct Commission = Allocated Amount × Slab Percentage × Area Sold**

### Progressive Commission Logic

When a sale causes the user to cross slab boundaries, the commission is calculated progressively:

**Example:**
- User has sold 200 sqyd total (Slab1: 0-250 sqyd @ 35%)
- Current sale: 150 sqyd
- Slab2 starts at 250 sqyd @ 40%

**Calculation:**
- First 50 sqyd (200-250): ₹1,500 × 35% × 50 = ₹26,250
- Remaining 100 sqyd (250-350): ₹1,500 × 40% × 100 = ₹60,000
- **Total Commission: ₹86,250**

### Volume Calculation

- **Total Volume = OWN Sales + TEAM Sales** (for the specific property type)
- Team sales include all downline users (recursive)
- Volume is calculated **before** the current sale (excluding current sale ID)
- Only **confirmed sales** are counted

### Commission Percentage

- Uses **weighted average percentage** for display
- Uses **primary slab percentage** (highest tier in the sale) for referral pool calculation

---

## Referral Commission Distribution (Level 2+)

### Referral Pool Calculation

**Referral Pool Per Unit = Allocated Amount - (Level 1 Commission Per Unit)**

Where:
- **Level 1 Commission Per Unit = Total Level 1 Commission ÷ Area Sold**

**Example:**
- Allocated Amount: ₹1,500 per sqyd
- Level 1 Commission: ₹86,250 (from progressive calculation)
- Area Sold: 150 sqyd
- Level 1 Commission Per Unit: ₹86,250 ÷ 150 = ₹575 per sqyd
- **Referral Pool Per Unit: ₹1,500 - ₹575 = ₹925 per sqyd**

### Referral Commission Formula

**Parent Referral Commission = (Parent Slab % - Child Slab %) × Allocated Amount × Area Sold**

**Example:**
- Parent Slab: Slab3 @ 45%
- Child Slab: Slab2 @ 40%
- Slab Difference: 45% - 40% = 5%
- Allocated Amount: ₹1,500 per sqyd
- Area Sold: 150 sqyd
- **Parent Commission Per Unit: ₹1,500 × 5% = ₹75 per sqyd**
- **Parent Total Commission: ₹75 × 150 = ₹11,250**

### Distribution Rules

1. **Slab Comparison**: Parent must have a **higher slab** (higher `sort_order`) than child to receive commission
   - If parent slab ≤ child slab, **no commission** is awarded
   - The "child" reference updates after each level (current parent becomes "child" for next level)

2. **Pool Limitation**: Commission is limited by remaining pool
   - **Actual Commission = min(Deserved Commission, Remaining Pool)**
   - If pool is exhausted, no further commissions are distributed

3. **Chain Traversal**: 
   - Starts from Level 2 (Level 1 is direct seller)
   - Traverses up the referral chain (`referred_by_user_id`)
   - Continues until:
     - No more parents exist, OR
     - Pool is exhausted (≤ ₹0.01 per unit remaining)

4. **Remaining Pool**: 
   - If pool is not exhausted and chain ends, remaining amount goes to **admin wallet**
   - This ensures all allocated commission is distributed

### Slab Calculation for Parents

- Parent's slab is calculated **excluding the current sale** to prevent double-counting
- Uses **OWN + TEAM volume** for the specific property type
- Slab is recalculated at each level to ensure accuracy

### Child Reference Update

After each parent check (whether they received commission or not):
- Current parent's slab becomes the "child" reference for the next level
- This ensures proper comparison at each level
- Example: Level 2 compares Parent vs Seller, Level 3 compares Grandparent vs Parent

---

## Complete Example

### Scenario
- **Project**: ABC Residency
- **Property Type**: Plot
- **Allocated Amount**: ₹1,500 per sqyd
- **Sale**: 150 sqyd plot

### User Hierarchy
- **Seller (Level 1)**: John - Slab2 (40%)
- **Parent (Level 2)**: Mary - Slab3 (45%)
- **Grandparent (Level 3)**: Tom - Slab4 (50%)

### John's Volume Before Sale
- Own Sales: 200 sqyd
- Team Sales: 100 sqyd
- **Total: 300 sqyd** (Slab2: 250-500 sqyd)

### Step 1: Calculate Level 1 Commission (Progressive)

**Volume Ranges:**
- Slab1: 0-250 sqyd @ 35%
- Slab2: 250-500 sqyd @ 40%
- Slab3: 500-750 sqyd @ 45%

**Progressive Calculation:**
- Current total before sale: 300 sqyd
- Sale: 150 sqyd
- First 50 sqyd (300-350): ₹1,500 × 40% × 50 = ₹30,000
- Remaining 100 sqyd (350-450): ₹1,500 × 40% × 100 = ₹60,000
- **Total Level 1 Commission: ₹90,000**

**Level 1 Commission Per Unit:** ₹90,000 ÷ 150 = ₹600 per sqyd

### Step 2: Calculate Referral Pool

**Referral Pool Per Unit:** ₹1,500 - ₹600 = ₹900 per sqyd
**Total Referral Pool:** ₹900 × 150 = ₹135,000

### Step 3: Distribute to Level 2 (Mary)

**Comparison:**
- Parent (Mary): Slab3 @ 45% (sort_order: 3)
- Child (John): Slab2 @ 40% (sort_order: 2)
- **Parent is higher** ✓

**Calculation:**
- Slab Difference: 45% - 40% = 5%
- Deserved Commission Per Unit: ₹1,500 × 5% = ₹75 per sqyd
- Deserved Total: ₹75 × 150 = ₹11,250
- Remaining Pool: ₹900 per sqyd
- **Actual Commission:** min(₹75, ₹900) × 150 = **₹11,250**

**Pool Remaining:** ₹900 - ₹75 = ₹825 per sqyd

**Child Reference Updated:** Mary's Slab3 becomes the new "child" reference

### Step 4: Distribute to Level 3 (Tom)

**Comparison:**
- Parent (Tom): Slab4 @ 50% (sort_order: 4)
- Child Reference (Mary): Slab3 @ 45% (sort_order: 3)
- **Parent is higher** ✓

**Calculation:**
- Slab Difference: 50% - 45% = 5%
- Deserved Commission Per Unit: ₹1,500 × 5% = ₹75 per sqyd
- Deserved Total: ₹75 × 150 = ₹11,250
- Remaining Pool: ₹825 per sqyd
- **Actual Commission:** min(₹75, ₹825) × 150 = **₹11,250**

**Pool Remaining:** ₹825 - ₹75 = ₹750 per sqyd

### Step 5: Check for More Parents

- No more parents in chain
- **Remaining Pool:** ₹750 × 150 = ₹112,500
- **Sent to Admin Wallet**

### Summary

| Level | User | Commission | Type |
|-------|------|------------|------|
| 1 | John | ₹90,000 | Direct |
| 2 | Mary | ₹11,250 | Referral |
| 3 | Tom | ₹11,250 | Referral |
| Admin | Admin | ₹112,500 | Remaining Pool |
| **Total** | | **₹225,000** | |

**Verification:** ₹1,500 × 150 sqyd = ₹225,000 ✓

---

## Important Rules

### 1. Slab Determination
- Slabs are based on **OWN + TEAM volume** for each property type
- Slabs are property-type-specific (user can have different slabs for Plot vs Villa)
- Slabs are recalculated after each sale

### 2. Progressive Commission
- Uses **OWN + TEAM volume** (same as slab calculation) for consistency
- Each portion of sale is paid at the slab rate for that volume range
- Weighted average percentage is calculated for display purposes

### 3. Referral Pool
- Pool = Allocated Amount - Actual Level 1 Commission Per Unit
- Uses actual progressive commission, not simple slab percentage
- Ensures accurate pool calculation when sales span multiple tiers

### 4. Referral Commission Eligibility
- Parent must have **higher slab** than child (by sort_order)
- Commission is limited by remaining pool
- Chain continues even if parent doesn't qualify (for next level comparison)

### 5. Remaining Pool
- If referral chain ends but pool remains, amount goes to admin
- This ensures 100% of allocated commission is distributed

### 6. Transaction Safety
- All commission distributions are wrapped in database transactions
- Remaining pool distribution is included in the same transaction
- Prevents partial distributions if errors occur

### 7. Duplicate Prevention
- System prevents duplicate commission distribution for the same sale
- Sale must be in 'confirmed' status to receive commissions

---

## Commission Recalculation

To recalculate all commissions:

```bash
# Preview what would be recalculated
php artisan commissions:recalculate --dry-run

# Recalculate all sales
php artisan commissions:recalculate

# Recalculate specific sale
php artisan commissions:recalculate --sale-id=123

# Force recalculation (even if already calculated)
php artisan commissions:recalculate --force
```

**Note:** Recalculation reverts previous commissions before recalculating to ensure accuracy.

---

## Key Formulas Summary

### Direct Commission
```
Level 1 Commission = Σ(Allocated Amount × Slab Percentage × Area in Tier)
```

### Referral Pool
```
Referral Pool Per Unit = Allocated Amount - (Level 1 Commission ÷ Area Sold)
```

### Referral Commission
```
Parent Commission Per Unit = (Parent Slab % - Child Slab %) × Allocated Amount
Parent Commission = min(Parent Commission Per Unit, Remaining Pool Per Unit) × Area Sold
```

### Volume Calculation
```
Total Volume = OWN Sales + TEAM Sales (for property type)
```

---

## Notes

- All calculations use **per-unit** basis for accuracy
- Commissions are distributed in **single transaction** for data integrity
- Slab upgrades happen **after** commission distribution
- System prevents circular references in referral chain
- Soft-deleted users are excluded from downline calculations
