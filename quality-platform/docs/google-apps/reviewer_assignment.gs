/**
 * Quality Dashboard reviewer auto-assignment for Google Sheets.
 *
 * Sheet requirements:
 * 1) Main sheet (default: "Quality Check"; checkmark-prefixed name is also supported)
 *    - Mentor is in column C.
 *    - Week headers are in row 1 and contain text like: Week 1, Week 2, ...
 *    - Reviewer name is written in the first column of each week block.
 *      Example blocks: E-H, I-L, M-P ... (reviewer at E, I, M, ...)
 *    - Reviewer role is written in the next column (F, J, N, ...).
 *
 * 2) Mentor/Coordinator sheet (default: "Mentor Coordinator")
 *    - Col A: Mentor
 *    - Col B: Coordinator
 *
 * 3) Reviewers sheet (default: "Reviewers")
 *    - Col A: Reviewer Name (required)
 *    - Col B: Reviewer Role (optional: Reviewer / Coordinator)
 *    - Col C: Weight (optional numeric, coordinator can be > 1 for higher load)
 */

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu("Quality Scheduler")
    .addItem("Assign Reviewers (All Weeks)", "assignReviewersAllWeeks")
    .addItem("Validate Assignments", "validateReviewerAssignments")
    .addItem("Highlight Duplicate Reviewers Per Tutor", "highlightDuplicateReviewersPerTutor")
    .addToUi();
}

function assignReviewersAllWeeks() {
  const cfg = getSchedulerConfig_();
  const ss = SpreadsheetApp.getActive();
  const qualitySheet = getSheetByAnyName_(
    ss,
    [cfg.qualitySheetName].concat(cfg.qualitySheetAliases || [])
  );
  const lastRow = qualitySheet.getLastRow();

  if (lastRow < cfg.firstDataRow) {
    SpreadsheetApp.getUi().alert("No tutor rows found.");
    return;
  }

  const weekInfo = detectWeekColumns_(qualitySheet, cfg);
  if (weekInfo.reviewerCols.length === 0) {
    throw new Error("No week headers found in row " + cfg.headerRow + ".");
  }

  const mentorCoordinatorMap = loadMentorCoordinatorMap_(ss, cfg);
  const coordinatorSet = new Set(Object.values(mentorCoordinatorMap));
  const reviewers = loadReviewers_(ss, cfg, coordinatorSet);

  if (reviewers.length === 0) {
    throw new Error("No reviewers found in sheet '" + cfg.reviewersSheetName + "'.");
  }

  const numRows = lastRow - cfg.firstDataRow + 1;
  const maxCol = Math.max(
    cfg.mentorCol,
    Math.max.apply(null, weekInfo.reviewerCols),
    cfg.writeRoleColumns ? Math.max.apply(null, weekInfo.roleCols) : 1
  );
  const values = qualitySheet.getRange(cfg.firstDataRow, 1, numRows, maxCol).getValues();

  const reviewerByKey = new Map(reviewers.map((reviewer) => [reviewer.key, reviewer]));
  const outReviewerByCol = {};
  weekInfo.reviewerCols.forEach((col) => {
    outReviewerByCol[col] = Array.from({ length: numRows }, (_, i) => [
      cfg.overwriteExistingAssignments ? "" : normalizeDisplayName_(values[i][col - 1]),
    ]);
  });

  const outRoleByCol = {};
  if (cfg.writeRoleColumns) {
    weekInfo.roleCols.forEach((col) => {
      outRoleByCol[col] = Array.from({ length: numRows }, (_, i) => [
        cfg.overwriteExistingAssignments ? "" : normalizeDisplayName_(values[i][col - 1]),
      ]);
    });
  }

  const usedByTutorRow = Array.from({ length: numRows }, () => new Set());
  const globalLoad = new Map(reviewers.map((reviewer) => [reviewer.key, 0]));

  if (!cfg.overwriteExistingAssignments) {
    for (let i = 0; i < numRows; i++) {
      for (let w = 0; w < weekInfo.reviewerCols.length; w++) {
        const reviewerCol = weekInfo.reviewerCols[w];
        const roleCol = weekInfo.roleCols[w];
        const existingReviewerKey = normalize_(values[i][reviewerCol - 1]);
        if (!existingReviewerKey) continue;

        usedByTutorRow[i].add(existingReviewerKey);
        if (globalLoad.has(existingReviewerKey)) {
          globalLoad.set(existingReviewerKey, globalLoad.get(existingReviewerKey) + 1);
        }

        if (cfg.writeRoleColumns && outRoleByCol[roleCol][i][0] === "") {
          const reviewer = reviewerByKey.get(existingReviewerKey);
          if (reviewer) outRoleByCol[roleCol][i][0] = reviewer.role;
        }
      }
    }
  }

  // Preflight: each tutor must have enough eligible unique reviewers for all needed week slots.
  const preflightErrors = [];
  for (let i = 0; i < numRows; i++) {
    const mentor = normalize_(values[i][cfg.mentorCol - 1]);
    const blocked = new Set();
    if (mentor) blocked.add(mentor);
    if (mentor && mentorCoordinatorMap[mentor]) blocked.add(mentorCoordinatorMap[mentor]);

    const eligibleReviewers = reviewers.filter((reviewer) => !blocked.has(reviewer.key));
    const alreadyUsed = cfg.overwriteExistingAssignments ? new Set() : usedByTutorRow[i];

    const neededAssignments = cfg.overwriteExistingAssignments
      ? weekInfo.reviewerCols.length
      : weekInfo.reviewerCols.filter((col) => normalize_(values[i][col - 1]) === "").length;

    const remainingEligibleCount = eligibleReviewers.filter(
      (reviewer) => !alreadyUsed.has(reviewer.key)
    ).length;

    if (remainingEligibleCount < neededAssignments) {
      const rowNum = cfg.firstDataRow + i;
      const tutorLabel = normalize_(values[i][cfg.tutorCodeCol - 1]) || "row " + rowNum;
      preflightErrors.push(
        "Row " +
          rowNum +
          " (" +
          tutorLabel +
          "): need " +
          neededAssignments +
          ", available " +
          remainingEligibleCount +
          " reviewer(s)."
      );
    }
  }

  if (preflightErrors.length > 0) {
    throw new Error(
      "Assignment cannot start due to capacity constraints:\n" +
        preflightErrors.slice(0, 20).join("\n")
    );
  }

  const conflicts = [];

  for (let weekIdx = 0; weekIdx < weekInfo.reviewerCols.length; weekIdx++) {
    const reviewerCol = weekInfo.reviewerCols[weekIdx];
    const roleCol = weekInfo.roleCols[weekIdx];
    const weekLabel = weekInfo.weekLabels[weekIdx];
    const weekLoad = new Map(reviewers.map((reviewer) => [reviewer.key, 0]));

    if (!cfg.overwriteExistingAssignments) {
      for (let i = 0; i < numRows; i++) {
        const existingReviewerKey = normalize_(values[i][reviewerCol - 1]);
        if (weekLoad.has(existingReviewerKey)) {
          weekLoad.set(existingReviewerKey, weekLoad.get(existingReviewerKey) + 1);
        }
      }
    }

    const rowOrder = shuffledIndexList_(numRows);

    for (let z = 0; z < rowOrder.length; z++) {
      const i = rowOrder[z];
      const rowNum = cfg.firstDataRow + i;
      const existingReviewerKey = normalize_(values[i][reviewerCol - 1]);

      if (!cfg.overwriteExistingAssignments && existingReviewerKey) {
        if (cfg.writeRoleColumns && outRoleByCol[roleCol][i][0] === "") {
          const existingReviewer = reviewerByKey.get(existingReviewerKey);
          if (existingReviewer) outRoleByCol[roleCol][i][0] = existingReviewer.role;
        }
        continue;
      }

      const mentor = normalize_(values[i][cfg.mentorCol - 1]);
      const blocked = new Set();
      if (mentor) blocked.add(mentor);
      if (mentor && mentorCoordinatorMap[mentor]) blocked.add(mentorCoordinatorMap[mentor]);

      const eligible = reviewers.filter(
        (reviewer) => !blocked.has(reviewer.key) && !usedByTutorRow[i].has(reviewer.key)
      );

      if (eligible.length === 0) {
        const tutorLabel = normalize_(values[i][cfg.tutorCodeCol - 1]) || "row " + rowNum;
        conflicts.push(
          "No eligible reviewer for " + tutorLabel + " at " + weekLabel + " (row " + rowNum + ")."
        );
        continue;
      }

      const chosen = pickBestReviewer_(eligible, weekLoad, globalLoad);
      outReviewerByCol[reviewerCol][i][0] = chosen.name;
      if (cfg.writeRoleColumns) outRoleByCol[roleCol][i][0] = chosen.role;

      usedByTutorRow[i].add(chosen.key);
      weekLoad.set(chosen.key, weekLoad.get(chosen.key) + 1);
      globalLoad.set(chosen.key, globalLoad.get(chosen.key) + 1);
    }
  }

  if (conflicts.length > 0) {
    throw new Error(
      "Assignment could not finish due to conflicts:\n" + conflicts.slice(0, 30).join("\n")
    );
  }

  weekInfo.reviewerCols.forEach((col) => {
    qualitySheet.getRange(cfg.firstDataRow, col, numRows, 1).setValues(outReviewerByCol[col]);
  });

  if (cfg.writeRoleColumns) {
    weekInfo.roleCols.forEach((col) => {
      qualitySheet.getRange(cfg.firstDataRow, col, numRows, 1).setValues(outRoleByCol[col]);
    });
  }

  SpreadsheetApp.flush();
  const summary = buildReviewerLoadSummary_(reviewers, globalLoad);
  SpreadsheetApp.getUi().alert(
    "Done. Assigned " +
      weekInfo.reviewerCols.length +
      " week(s) for " +
      numRows +
      " tutor(s).\n\n" +
      summary
  );
}

function validateReviewerAssignments() {
  const cfg = getSchedulerConfig_();
  const ss = SpreadsheetApp.getActive();
  const qualitySheet = getSheetByAnyName_(
    ss,
    [cfg.qualitySheetName].concat(cfg.qualitySheetAliases || [])
  );
  const lastRow = qualitySheet.getLastRow();

  if (lastRow < cfg.firstDataRow) {
    SpreadsheetApp.getUi().alert("No data rows.");
    return;
  }

  const weekInfo = detectWeekColumns_(qualitySheet, cfg);
  if (weekInfo.reviewerCols.length === 0) {
    SpreadsheetApp.getUi().alert("No week columns detected.");
    return;
  }

  const mentorCoordinatorMap = loadMentorCoordinatorMap_(ss, cfg);
  const numRows = lastRow - cfg.firstDataRow + 1;
  const maxCol = Math.max(cfg.mentorCol, Math.max.apply(null, weekInfo.reviewerCols));
  const values = qualitySheet.getRange(cfg.firstDataRow, 1, numRows, maxCol).getValues();

  // Clear previous backgrounds.
  weekInfo.reviewerCols.forEach((col) => {
    qualitySheet.getRange(cfg.firstDataRow, col, numRows, 1).setBackground(null);
  });

  const duplicateColor = "#f4cccc";
  const conflictColor = "#fce5cd";
  const bgByCol = {};
  weekInfo.reviewerCols.forEach((col) => {
    bgByCol[col] = Array.from({ length: numRows }, () => [null]);
  });

  const issues = [];

  for (let i = 0; i < numRows; i++) {
    const mentor = normalize_(values[i][cfg.mentorCol - 1]);
    const coordinator = mentor ? mentorCoordinatorMap[mentor] : "";
    const seenReviewers = new Map();

    weekInfo.reviewerCols.forEach((col, weekIdx) => {
      const reviewer = normalize_(values[i][col - 1]);
      if (!reviewer) return;

      if (!seenReviewers.has(reviewer)) seenReviewers.set(reviewer, []);
      seenReviewers.get(reviewer).push(col);

      if (mentor && reviewer === mentor) {
        bgByCol[col][i][0] = conflictColor;
        issues.push(
          "Row " +
            (cfg.firstDataRow + i) +
            " " +
            weekInfo.weekLabels[weekIdx] +
            ": reviewer equals mentor."
        );
      } else if (coordinator && reviewer === coordinator) {
        bgByCol[col][i][0] = conflictColor;
        issues.push(
          "Row " +
            (cfg.firstDataRow + i) +
            " " +
            weekInfo.weekLabels[weekIdx] +
            ": reviewer equals mentor coordinator."
        );
      }
    });

    for (const pair of seenReviewers.entries()) {
      const cols = pair[1];
      if (cols.length > 1) {
        cols.forEach((col) => {
          bgByCol[col][i][0] = duplicateColor;
        });
      }
    }
  }

  weekInfo.reviewerCols.forEach((col) => {
    qualitySheet.getRange(cfg.firstDataRow, col, numRows, 1).setBackgrounds(bgByCol[col]);
  });

  if (issues.length === 0) {
    SpreadsheetApp.getUi().alert("Validation passed. No rule violations found.");
    return;
  }

  SpreadsheetApp.getUi().alert(
    "Validation finished with " +
      issues.length +
      " issue(s).\n\n" +
      issues.slice(0, 20).join("\n")
  );
}

function highlightDuplicateReviewersPerTutor() {
  const cfg = getSchedulerConfig_();
  const sh = getSheetByAnyName_(
    SpreadsheetApp.getActive(),
    [cfg.qualitySheetName].concat(cfg.qualitySheetAliases || [])
  );

  const lastRow = sh.getLastRow();
  if (lastRow < cfg.firstDataRow) return;

  const weekInfo = detectWeekColumns_(sh, cfg);
  const reviewerCols = weekInfo.reviewerCols;
  if (reviewerCols.length === 0) return;

  const maxCol = Math.max.apply(null, reviewerCols);
  const numRows = lastRow - cfg.firstDataRow + 1;
  const values = sh.getRange(cfg.firstDataRow, 1, numRows, maxCol).getValues();

  reviewerCols.forEach((col) => {
    sh.getRange(cfg.firstDataRow, col, numRows, 1).setBackground(null);
  });

  const bgByCol = {};
  reviewerCols.forEach((col) => {
    bgByCol[col] = Array.from({ length: numRows }, () => [null]);
  });

  for (let i = 0; i < numRows; i++) {
    const seen = new Map();
    reviewerCols.forEach((col) => {
      const reviewer = normalize_(values[i][col - 1]);
      if (!reviewer) return;
      if (!seen.has(reviewer)) seen.set(reviewer, []);
      seen.get(reviewer).push(col);
    });

    for (const pair of seen.entries()) {
      const cols = pair[1];
      if (cols.length > 1) {
        cols.forEach((col) => {
          bgByCol[col][i][0] = "#f4cccc";
        });
      }
    }
  }

  reviewerCols.forEach((col) => {
    sh.getRange(cfg.firstDataRow, col, numRows, 1).setBackgrounds(bgByCol[col]);
  });
}

function getSchedulerConfig_() {
  return {
    qualitySheetName: "Quality Check",
    qualitySheetAliases: ["\u2705Quality Check"],
    mentorCoordinatorSheetName: "Mentor Coordinator",
    reviewersSheetName: "Reviewers",
    headerRow: 1,
    firstDataRow: 3,
    tutorCodeCol: 2,
    mentorCol: 3,
    writeRoleColumns: true,
    // false = keep existing assignments and fill only blank week slots.
    overwriteExistingAssignments: false,
    weekHeaderRegex: /^week\b/i,
    weekBlockSize: 4,
    defaultReviewerWeight: 1.0,
    defaultCoordinatorWeight: 1.35,
  };
}

function detectWeekColumns_(sheet, cfg) {
  const lastCol = sheet.getLastColumn();
  const headerValues = sheet.getRange(cfg.headerRow, 1, 1, lastCol).getValues()[0];
  const reviewerCols = [];
  const weekLabels = [];

  for (let col = 1; col <= lastCol; col++) {
    const raw = (headerValues[col - 1] || "").toString().trim();
    if (!raw) continue;
    if (!cfg.weekHeaderRegex.test(raw)) continue;

    reviewerCols.push(col);
    weekLabels.push(raw);
  }

  // Fallback for old layout (E, I, M, Q...) in case headers are missing.
  if (reviewerCols.length === 0 && lastCol >= 5) {
    for (let col = 5, week = 1; col <= lastCol; col += cfg.weekBlockSize, week++) {
      reviewerCols.push(col);
      weekLabels.push("Week " + week);
    }
  }

  const roleCols = reviewerCols.map((col) => col + 1);
  return { reviewerCols, roleCols, weekLabels };
}

function loadMentorCoordinatorMap_(ss, cfg) {
  const mapSheet = mustGetSheet_(ss, cfg.mentorCoordinatorSheetName);
  const lastRow = mapSheet.getLastRow();
  if (lastRow < 2) return {};

  const rows = mapSheet.getRange(2, 1, lastRow - 1, 2).getValues();
  const mentorToCoordinator = {};

  rows.forEach((row) => {
    const mentor = normalize_(row[0]);
    const coordinator = normalize_(row[1]);
    if (!mentor || !coordinator) return;
    mentorToCoordinator[mentor] = coordinator;
  });

  return mentorToCoordinator;
}

function loadReviewers_(ss, cfg, coordinatorSet) {
  const reviewersSheet = mustGetSheet_(ss, cfg.reviewersSheetName);
  const lastRow = reviewersSheet.getLastRow();
  if (lastRow < 2) return [];

  const rows = reviewersSheet.getRange(2, 1, lastRow - 1, 3).getValues();
  const seen = new Set();
  const reviewers = [];

  rows.forEach((row) => {
    const displayName = normalizeDisplayName_(row[0]);
    const key = normalize_(row[0]);
    if (!key || seen.has(key)) return;

    const explicitRole = normalizeRole_(row[1]);
    const role =
      explicitRole || (coordinatorSet.has(key) ? "Coordinator" : "Reviewer");

    const rawWeight = Number(row[2]);
    const weight =
      Number.isFinite(rawWeight) && rawWeight > 0
        ? rawWeight
        : role === "Coordinator"
        ? cfg.defaultCoordinatorWeight
        : cfg.defaultReviewerWeight;

    reviewers.push({
      name: displayName,
      key: key,
      role: role,
      weight: weight,
    });
    seen.add(key);
  });

  return reviewers;
}

function pickBestReviewer_(eligible, weekLoad, globalLoad) {
  let bestScore = Number.POSITIVE_INFINITY;
  let best = [];

  eligible.forEach((reviewer) => {
    const weekCount = weekLoad.get(reviewer.key) || 0;
    const totalCount = globalLoad.get(reviewer.key) || 0;
    const score = (weekCount + totalCount * 0.35) / reviewer.weight;

    if (score < bestScore - 1e-9) {
      bestScore = score;
      best = [reviewer];
      return;
    }

    if (Math.abs(score - bestScore) <= 1e-9) {
      best.push(reviewer);
    }
  });

  return best[Math.floor(Math.random() * best.length)];
}

function buildReviewerLoadSummary_(reviewers, globalLoad) {
  const lines = reviewers
    .map((reviewer) => {
      const count = globalLoad.get(reviewer.key) || 0;
      return (
        reviewer.name +
        " (" +
        reviewer.role +
        ", weight " +
        reviewer.weight +
        "): " +
        count
      );
    })
    .sort((a, b) => a.localeCompare(b));

  return lines.slice(0, 20).join("\n");
}

function shuffledIndexList_(size) {
  const list = Array.from({ length: size }, (_, i) => i);
  for (let i = list.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    const temp = list[i];
    list[i] = list[j];
    list[j] = temp;
  }
  return list;
}

function normalizeRole_(value) {
  const raw = normalize_(value);
  if (!raw) return "";
  if (raw === "coordinator") return "Coordinator";
  if (raw === "reviewer") return "Reviewer";
  return "";
}

function normalizeDisplayName_(value) {
  return ((value || "").toString().trim().replace(/\s+/g, " ")) || "";
}

function normalize_(value) {
  return ((value || "").toString().trim().replace(/\s+/g, " ").toLowerCase()) || "";
}

function mustGetSheet_(ss, name) {
  const sheet = ss.getSheetByName(name);
  if (!sheet) throw new Error("Sheet '" + name + "' not found.");
  return sheet;
}

function getSheetByAnyName_(ss, names) {
  for (let i = 0; i < names.length; i++) {
    const name = names[i];
    const sheet = ss.getSheetByName(name);
    if (sheet) return sheet;
  }
  throw new Error("None of these sheets were found: " + names.join(", "));
}
