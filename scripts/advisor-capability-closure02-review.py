#!/usr/bin/env python3
"""Reuse the owner-verified launcher; separate source, actor and browser namespace."""
import importlib.util,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('closure_launcher',ROOT/'scripts/advisor-full-grants-qa01-review.py');review=importlib.util.module_from_spec(s);s.loader.exec_module(review)
review.BRANCH='feature/mxmed-advisor-capability-closure02'
review.ADVISOR_EMAIL='full_grants_closure@example.test'
review.ADVISOR_RECORD='full-closure.json'
review.REVIEW_PREFIX='advisor-capability-closure02'
review.LAUNCHER=pathlib.Path(__file__).resolve()
if __name__=='__main__':review.main()
