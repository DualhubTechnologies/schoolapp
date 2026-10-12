    {{-- ── Module showcase ── --}}
    <section class="section section-alt" id="modules">
        <div class="container">
            <div class="section-head" data-reveal>
                <div class="eyebrow">Made for how Ugandan schools work</div>
                <h2>Less time on paperwork, more time on learners</h2>
            </div>

            <div class="showcase">
                <div class="show" data-reveal>
                    <div class="show-copy">
                        <div class="eyebrow">Finance</div>
                        <h3>See income against spending, any time</h3>
                        <p>The bursar records expenses and other income as they happen. SchoolHub puts them beside the fees received and the payroll, so the director sees where the money came from, where it went and what is left, for a term, a year or any dates.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} School fees and salaries fill in by themselves</li>
                            <li>{!! $check !!} Expenses and other income by category, against a term budget</li>
                            <li>{!! $check !!} Bank accounts and bank reconciliation</li>
                        </ul>
                    </div>
                    <div class="show-media" aria-hidden="true">
                        <div class="doc">
                            <div class="doc-head">
                                <div><strong>Income vs expenditure</strong><span>Term II · 2026</span></div>
                                <span class="tag tag-green">Within budget</span>
                            </div>
                            <div class="doc-rows">
                                <div><span>School fees</span><b>UGX 84,200,000</b></div>
                                <div><span>Other income (uniforms, canteen)</span><b>UGX 3,100,000</b></div>
                                <div><span>Salaries &amp; wages</span><b>− UGX 38,400,000</b></div>
                                <div><span>Food &amp; kitchen</span><b>− UGX 14,600,000</b></div>
                                <div><span>Fuel, utilities &amp; stationery</span><b>− UGX 8,900,000</b></div>
                            </div>
                            <div class="doc-total"><span>Balance</span><span>UGX 25,400,000</span></div>
                        </div>
                    </div>
                </div>

                <div class="show" data-reveal>
                    <div class="show-copy">
                        <div class="eyebrow">O-Level · new curriculum</div>
                        <h3>New-curriculum report cards, ready to print</h3>
                        <p>Teachers enter the Activities of Integration and the End of Term marks. SchoolHub adds the 20% school-based mark to the 80% exam, grades every subject and prints report cards for the whole class, like this one.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} Activities of Integration make up the 20% school-based mark</li>
                            <li>{!! $check !!} Grades A–E: Exceptional, Outstanding, Satisfactory, Basic, Elementary</li>
                            <li>{!! $check !!} Positions, attendance, comments, promotion and the head teacher's signature</li>
                            <li>{!! $check !!} A QR code on every card, so anyone can check it is genuine</li>
                        </ul>
                    </div>
                    <div class="show-media">
                        <a class="rc-shot" href="{{ asset('images/site/report-card-o-level.jpg') }}" target="_blank" rel="noopener" title="Open the full report card">
                            <img src="{{ asset('images/site/report-card-o-level.jpg') }}" alt="An O-Level report card printed by SchoolHub: subjects, CA and End of Term marks, grades A–E with achievement, position, attendance, comments and the head teacher&rsquo;s signature" width="1500" height="2156" loading="lazy">
                        </a>
                        <p class="rc-caption">Classic design · navy and gold · QR code to verify</p>
                    </div>
                </div>

                <div class="show" data-reveal>
                    <div class="show-copy">
                        <div class="eyebrow">A-Level</div>
                        <h3>UACE grades and points, worked out for you</h3>
                        <p>Enter the marks for each paper. SchoolHub grades every paper, works out each principal subject's grade by UNEB's rules, adds the subsidiaries and gives every learner their points out of 20.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} Subject combinations, with General Paper and a subsidiary</li>
                            <li>{!! $check !!} Principal grades A–F worked out from each paper, by UNEB's rules</li>
                            <li>{!! $check !!} Points out of 20 and the result for every learner</li>
                            <li>{!! $check !!} Customised for your school: your crest, colours, layout and the parts you print</li>
                        </ul>
                    </div>
                    <div class="show-media">
                        <a class="rc-shot" href="{{ asset('images/site/report-card-a-level.jpg') }}" target="_blank" rel="noopener" title="Open the full report card">
                            <img src="{{ asset('images/site/report-card-a-level.jpg') }}" alt="An A-Level report card printed by SchoolHub: paper marks, principal grades, points out of 20, result, comments and the head teacher&rsquo;s signature" width="1500" height="2156" loading="lazy">
                        </a>
                        <p class="rc-caption">Modern design · green, gold border and faint crest · the same school, a different look</p>
                    </div>
                </div>

                <div class="show" data-reveal>
                    <div class="show-copy">
                        <div class="eyebrow">Fees</div>
                        <h3>Know every balance, every day</h3>
                        <p>Bill a whole class in one go, receive payments at the bursar's desk and print a receipt instantly. Balances update the moment money comes in.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} Fee structures per class, term and residency (day or boarding)</li>
                            <li>{!! $check !!} Discounts and bursaries applied automatically</li>
                            <li>{!! $check !!} Reminder letters for parents with outstanding balances</li>
                        </ul>
                    </div>
                    <div class="show-media" aria-hidden="true">
                        <div class="doc">
                            <div class="doc-head">
                                <div><strong>Official receipt</strong><span>No. 001042 · 14 Jul 2026</span></div>
                                <span class="tag tag-green">Paid</span>
                            </div>
                            <div class="doc-rows">
                                <div><span>Student</span><b>Nakato Sarah · S.2 East</b></div>
                                <div><span>Tuition — Term II</span><b>UGX 850,000</b></div>
                                <div><span>Paid by</span><b>Mobile money</b></div>
                                <div><span>Balance remaining</span><b>UGX 400,000</b></div>
                            </div>
                            <div class="doc-total"><span>Amount received</span><span>UGX 450,000</span></div>
                        </div>
                    </div>
                </div>

                <div class="show" data-reveal>
                    <div class="show-copy">
                        <div class="eyebrow">Payroll</div>
                        <h3>Pay staff correctly, on time</h3>
                        <p>Run the month's payroll in minutes. Statutory deductions are calculated for you, and every staff member gets a clear payslip.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} PAYE, NSSF (5% + 10%) and Local Service Tax</li>
                            <li>{!! $check !!} Allowances, deductions, advances and arrears</li>
                            <li>{!! $check !!} NSSF schedules ready to submit</li>
                        </ul>
                    </div>
                    <div class="show-media" aria-hidden="true">
                        <div class="doc">
                            <div class="doc-head">
                                <div><strong>Payslip — July 2026</strong><span>Ssemwanga Peter · Teacher</span></div>
                            </div>
                            <div class="doc-rows">
                                <div><span>Basic salary</span><b>UGX 1,200,000</b></div>
                                <div><span>Housing allowance</span><b>UGX 150,000</b></div>
                                <div><span>PAYE</span><b>− UGX 307,000</b></div>
                                <div><span>NSSF (5%)</span><b>− UGX 67,500</b></div>
                            </div>
                            <div class="doc-total"><span>Net pay</span><span>UGX 975,500</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
